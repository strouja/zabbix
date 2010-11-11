<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
/**
 * Description of class
 * Produces ZABBIX object for more comfortable usage of jQuery tabed view
 * @author Aly
 */
class CTabView extends CDiv{
	protected $id = null;
	protected $tabs = null;
	protected $headers = null;

	public function __construct($id='tabs'){
		$this->id = $id;

		$this->headers = new CList();
		$this->tabs = array();

		parent::__construct();
		$this->setAttribute('id',$this->id);
	}

	public function addTab($id, $header, $body){
		$this->headers->addItem(new CLink($header, '#'.$id, null, null, false));
		
		$this->tabs[$id] = new CDiv($body);
		$this->tabs[$id]->setAttribute('id', $id);
	}

	public function toString($destroy=true){
		$this->addItem($this->headers);
		$this->addItem($this->tabs);

		zbx_add_post_js('jQuery(function() { jQuery( "#'.$this->id.'" ).tabs(); });');
		return parent::toString($destroy);
	}
}
?>