<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


/**
 * Shows surrogate screen filled with graphs generated by selected graph prototype or preview of graph prototype.
 */
class CScreenLldGraph extends CScreenLldGraphBase {

	/**
	 * @var array
	 */
	protected $createdGraphIds = array();

	/**
	 * @var array
	 */
	protected $graphPrototype = null;

	/**
	 * Adds graph items to surrogate screen.
	 */
	protected function addSurrogateScreenItems() {
		$createdGraphIds = $this->getCreatedGraphIds();
		$this->addGraphsToSurrogateScreen($createdGraphIds);
	}

	/**
	 * Retrieves graphs created for graph prototype given as resource for this screen item
	 * and returns array of the graph IDs.
	 *
	 * @return array
	 */
	protected function getCreatedGraphIds() {
		if (!$this->createdGraphIds) {
			$hostId = $this->getCurrentHostId();

			// get all created (discovered) graphs for current graph host
			$allCreatedGraphs = API::Graph()->get(array(
				'output' => array('name'),
				'hostids' => array($hostId),
				'selectGraphDiscovery' => array('graphid', 'parent_graphid'),
				'filter' => array('flags' => ZBX_FLAG_DISCOVERY_CREATED),
			));

			// get graph prototype id to be cross checked with all graphs for current host
			$graphPrototypeId = $this->getGraphPrototypeId();

			if ($graphPrototypeId) {
				// collect those graph IDs where parent graph is graph prototype selected for this screen item as resource
				foreach ($allCreatedGraphs as $graph) {
					if ($graph['graphDiscovery']['parent_graphid'] == $graphPrototypeId) {
						$this->createdGraphIds[$graph['graphid']] = $graph['name'];
					}
				}
				natsort($this->createdGraphIds);
				$this->createdGraphIds = array_keys($this->createdGraphIds);
			}
		}

		return $this->createdGraphIds;
	}

	/**
	 * Makes and adds graph items to surrogate screen.
	 *
	 * @param array $graphIds
	 */
	protected function addGraphsToSurrogateScreen(array $graphIds) {
		$screenItemTemplate = $this->getScreenItemTemplate(SCREEN_RESOURCE_GRAPH);

		$screenItems = array();
		foreach ($graphIds as $graphId) {
			$screenItem = $screenItemTemplate;

			$screenItem['resourceid'] = $graphId;
			$screenItem['screenitemid'] = $graphId;

			$screenItems[] = $screenItem;
		}

		$this->addItemsToSurrogateScreen($screenItems);
	}

	/**
	 * @inheritdoc
	 */
	protected function getHostIdFromScreenItemResource() {
		$graphPrototype = $this->getGraphPrototype();

		return $graphPrototype['discoveryRule']['hostid'];
	}

	/**
	 * Return graph prototype ID of either graph prototype selected in configuration, or, if dynamic mode is enabled,
	 * try to find graph prototype with same name in selected host.
	 *
	 * @return mixed
	 */
	protected function getGraphPrototypeId() {
		if ($this->screenitem['dynamic'] == SCREEN_DYNAMIC_ITEM && $this->hostid) {
			$currentGraphPrototype = API::GraphPrototype()->get(array(
				'output' => array('name'),
				'graphids' => array($this->screenitem['resourceid'])
			));
			$currentGraphPrototype = reset($currentGraphPrototype);

			$selectedHostGraphPrototype = API::GraphPrototype()->get(array(
				'output' => array('graphid'),
				'hostids' => array($this->hostid),
				'filter' => array('name' => $currentGraphPrototype['name'])
			));

			if ($selectedHostGraphPrototype) {
				$selectedHostGraphPrototype = reset($selectedHostGraphPrototype);
				$graphPrototypeId = $selectedHostGraphPrototype['graphid'];
			}
			else {
				$graphPrototypeId = null;
			}
		}
		else {
			$graphPrototypeId = $this->screenitem['resourceid'];
		}

		return $graphPrototypeId;
	}

	/**
	 * @inheritdoc
	 */
	protected function mustShowPreview() {
		$createdGraphIds = $this->getCreatedGraphIds();

		if ($createdGraphIds) {
			return false;
		}
		else {
			return true;
		}
	}

	/**
	 * @inheritdoc
	 */
	protected function getPreview() {
		$graphPrototype = $this->getGraphPrototype();

		unset($graphPrototype['graphid']);

		switch ($graphPrototype['graphtype']) {
			case GRAPH_TYPE_NORMAL:
			case GRAPH_TYPE_STACKED:
				$url = 'chart3.php';
				break;

			case GRAPH_TYPE_EXPLODED:
			case GRAPH_TYPE_3D_EXPLODED:
			case GRAPH_TYPE_3D:
			case GRAPH_TYPE_PIE:
				$url = 'chart7.php';
				break;

			case GRAPH_TYPE_BAR:
			case GRAPH_TYPE_COLUMN:
			case GRAPH_TYPE_BAR_STACKED:
			case GRAPH_TYPE_COLUMN_STACKED:
				$url = 'chart_bar.php';
				break;

			default:
				show_error_message(_('Graph prototype not found.'));
				exit;
		}

		$queryParams = array(
			'items' => $graphPrototype['gitems'],
			'graphtype' => $graphPrototype['graphtype'],
			'period' => 3600,
			'legend' => $graphPrototype['show_legend'],
			'graph3d' => $graphPrototype['show_3d'],
			'width' => $this->screenitem['width'],
			'height' => $this->screenitem['height'],
			'name' => $graphPrototype['name']
		);

		$url .= '?'.http_build_query($queryParams);

		$img = new CImg($url);

		return $img;
	}

	/**
	 * @return array
	 */
	protected function getGraphPrototype() {
		if (!$this->graphPrototype) {
			$options = array(
				'output' => array('name', 'graphtype', 'show_legend', 'show_3d'),
				'graphids' => array($this->getGraphPrototypeId()),
				'selectDiscoveryRule' => array('hostid'),
				'selectGraphItems' => array(
					'gitemid', 'itemid', 'sortorder', 'flags', 'type', 'calc_fnc',  'drawtype', 'yaxisside', 'color'
				)
			);
			$graphPrototype = API::GraphPrototype()->get($options);
			$this->graphPrototype = reset($graphPrototype);
		}

		return $this->graphPrototype;
	}
}
