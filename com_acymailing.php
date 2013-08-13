<?php

/**
 * @author     Branko Wilhelm <branko.wilhelm@gmail.com>
 * @link       http://www.z-index.net
 * @copyright  (c) 2013 Branko Wilhelm
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
 
defined('_JEXEC') or die;

final class xmap_com_acymailing {

	private static $views = array('archive', 'lists');

	public static function getTree(&$xmap, &$parent, &$params) {
		$uri = new JUri($parent->link);

		if(!in_array($uri->getVar('view'), self::$views)) {
			return;
		}

		$include_newsletter = JArrayHelper::getValue($params, 'include_newsletter');
		$include_newsletter = ($include_newsletter == 1 || ($include_newsletter == 2 && $xmap->view == 'xml') || ($include_newsletter == 3 && $xmap->view == 'html'));
		$params['include_newsletter'] = $include_newsletter;

		$show_unauth = JArrayHelper::getValue($params, 'show_unauth');
		$show_unauth = ($show_unauth == 1 || ( $show_unauth == 2 && $xmap->view == 'xml') || ( $show_unauth == 3 && $xmap->view == 'html'));
		$params['show_unauth'] = $show_unauth;

		$params['groups'] = implode(',', JFactory::getUser()->getAuthorisedViewLevels());

		$priority = JArrayHelper::getValue($params, 'list_priority', $parent->priority);
		$changefreq = JArrayHelper::getValue($params, 'list_changefreq', $parent->changefreq);

		if($priority == -1) {
			$priority = $parent->priority;
		}

		if($changefreq == -1) {
			$changefreq = $parent->changefreq;
		}
			
		$params['list_priority'] = $priority;
		$params['list_changefreq'] = $changefreq;

		$priority = JArrayHelper::getValue($params, 'newsletter_priority', $parent->priority);
		$changefreq = JArrayHelper::getValue($params, 'newsletter_changefreq', $parent->changefreq);

		if($priority == -1) {
			$priority = $parent->priority;
		}

		if($changefreq == -1) {
			$changefreq = $parent->changefreq;
		}

		$params['newsletter_priority'] = $priority;
		$params['newsletter_changefreq'] = $changefreq;

		switch($uri->getVar('view')) {
			case 'lists':
				self::getListsTree($xmap, $parent, $params);
				break;
					
			case 'archive':
				$item = JFactory::getApplication()->getMenu()->getItem($parent->id);
				self::getNewsletter($xmap, $parent, $params, $item->params->get('listid'));
				break;
		}
	}

	private static function getListsTree(&$xmap, &$parent, &$params) {
		$db = JFactory::getDbo();

		$query = $db->getQuery(true)
				->select(array('listid', 'name'))
				->from('#__acymailing_list AS l')
				->where('l.published = 1')
				->where('l.type = ' . $db->Quote('list'))
				->order('l.ordering');

		if (!$params['show_unauth']) {
			$query->where('l.visible = 1');
		}

		$db->setQuery($query);
		$rows = $db->loadObjectList();

		if(empty($rows)) {
			return;
		}

		$xmap->changeLevel(1);

		foreach($rows as $row) {
			$node = new stdclass;
			$node->id = $parent->id;
			$node->name = $row->name;
			$node->uid = $parent->uid . '_lid_' . $row->listid;
			$node->browserNav = $parent->browserNav;
			$node->priority = $params['list_priority'];
			$node->changefreq = $params['list_changefreq'];
			$node->link = JRoute::_('index.php?option=com_acymailing&ctrl=archive&listid=' . $row->listid . '&Itemid=' . $parent->id);
				
			if ($xmap->printNode($node) !== false && $params['include_newsletter']) {
				self::getNewsletter($xmap, $parent, $params, $row->listid);
			}
		}

		$xmap->changeLevel(-1);
	}

	private static function getNewsletter(&$xmap, &$parent, &$params, $listid) {
		$db = JFactory::getDbo();

		$query = $db->getQuery(true)
				->select(array('m.subject', 'm.mailid', 'l.listid'))
				->from('#__acymailing_listmail AS lm')
				->join('INNER', '#__acymailing_mail AS m ON(m.mailid = lm.mailid)')
				->join('INNER', '#__acymailing_list AS l ON(lm.listid = l.listid)')
				->where('l.listid = ' . $db->Quote($listid))
				->where('l.published = 1')
				->where('m.published = 1')
				->order('m.created');

		if (!$params['show_unauth']) {
			$query->where('l.visible = 1');
			$query->where('m.visible = 1');
		}

		$db->setQuery($query);
		$rows = $db->loadObjectList();
		
		if(empty($rows)) {
			return;
		}

		$xmap->changeLevel(1);

		foreach($rows as $row) {
			$node = new stdclass;
			$node->id = $parent->id;
			$node->name = $row->subject;
			$node->uid = $parent->uid . '_' . $row->mailid;
			$node->browserNav = $parent->browserNav;
			$node->priority = $params['newsletter_priority'];
			$node->changefreq = $params['newsletter_changefreq'];
			$node->link = JRoute::_('index.php?option=com_acymailing&ctrl=archive&task=view&listid=' . $row->listid . '&mailid=' . $row->mailid . '&Itemid=' . $parent->id);
				
			$xmap->printNode($node);
		}

		$xmap->changeLevel(-1);
	}
}