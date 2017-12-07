/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "zbxmocktest.h"
#include "zbxmockdata.h"

#include "common.h"
#include "sysinfo.h"

void	zbx_mock_test_entry(void **state)
{
	AGENT_REQUEST		request;
	AGENT_RESULT 		param_result;

	zbx_mock_error_t	error;
	zbx_mock_handle_t	init_param_handle;
	const char		*init_param;
	zbx_mock_handle_t	expected_param_value_handle;
	const char		*expected_param_value_string;
	zbx_uint64_t 		expected_param_value =0;

	int			expected_result =FAIL, actual_result =FAIL;

	ZBX_UNUSED(state);

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("param", &init_param_handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(init_param_handle, &init_param)))
	{
		fail_msg("Cannot get input param from test case data: %s", zbx_mock_error_string(error));
	}

	if (ZBX_MOCK_NO_PARAMETER == (error = zbx_mock_out_parameter("result", &expected_param_value_handle)))
		fail_msg("Cannot get expected key from test case data: %s", zbx_mock_error_string(error));
	else if (ZBX_MOCK_SUCCESS != error || ZBX_MOCK_SUCCESS != (error = zbx_mock_string(expected_param_value_handle, &expected_param_value_string)))
		fail_msg("Cannot get expected parameters from test case data: %s", zbx_mock_error_string(error));
	else
	{
		if (1 == sscanf(expected_param_value_string, ZBX_FS_UI64 "\n", &expected_param_value))
			expected_result = SYSINFO_RET_OK;
		else
			expected_result = SYSINFO_RET_FAIL;
	}

	init_request(&request);
	init_result(&param_result);

	parse_item_key(init_param, &request);

	if (expected_result != (actual_result = NET_IF_OUT(&request,&param_result)))
	{
		fail_msg("Got %s instead of %s as a result.", zbx_result_string(actual_result),
			zbx_result_string(expected_result));
	}

	if (SYSINFO_RET_OK == expected_result)
	{
		if (expected_param_value != *GET_UI64_RESULT(&param_result))
			fail_msg("For key = '%s' got '" ZBX_FS_UI64 "' instead of '%s' as a value.", init_param,
				*GET_UI64_RESULT(&param_result), expected_param_value_string);
	}

	if (SYSINFO_RET_FAIL == expected_result)
	{
		if (0 != strcmp(expected_param_value_string, *GET_MSG_RESULT(&param_result)))
			fail_msg("Got '%s' instead of '%s' as a value.", *GET_MSG_RESULT(&param_result), expected_param_value_string);
	}

	free_request(&request);
}
