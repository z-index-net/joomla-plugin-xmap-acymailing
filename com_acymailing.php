<?php

/**
 * @author     Branko Wilhelm <branko.wilhelm@gmail.com>
 * @link       http://www.z-index.net
 * @copyright  (c) 2013 - 2015 Branko Wilhelm
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('_JEXEC') or die;

class xmap_com_acymailing
{
    /**
     * @var array
     */
    private static $views = array('archive', 'lists');

    /**
     * @var bool
     */
    private static $enabled = false;

    public function __construct()
    {
        self::$enabled = JComponentHelper::isEnabled('com_acymailing');
    }

    /**
     * @param XmapDisplayerInterface $xmap
     * @param stdClass $parent
     * @param array $params
     *
     * @throws Exception
     */
    public static function getTree($xmap, stdClass $parent, array &$params)
    {
        $uri = new JUri($parent->link);

        if (!self::$enabled || !in_array($uri->getVar('view'), self::$views))
        {
            return;
        }

        $params['groups'] = implode(',', JFactory::getUser()->getAuthorisedViewLevels());

        $params['include_newsletter'] = JArrayHelper::getValue($params, 'include_newsletter', 1);
        $params['include_newsletter'] = ($params['include_newsletter'] == 1 || ($params['include_newsletter'] == 2 && $xmap->view == 'xml') || ($params['include_newsletter'] == 3 && $xmap->view == 'html'));

        $params['show_unauth'] = JArrayHelper::getValue($params, 'show_unauth', 0);
        $params['show_unauth'] = ($params['show_unauth'] == 1 || ($params['show_unauth'] == 2 && $xmap->view == 'xml') || ($params['show_unauth'] == 3 && $xmap->view == 'html'));

        $params['list_priority'] = JArrayHelper::getValue($params, 'list_priority', $parent->priority);
        $params['list_changefreq'] = JArrayHelper::getValue($params, 'list_changefreq', $parent->changefreq);

        if ($params['list_priority'] == -1)
        {
            $params['list_priority'] = $parent->priority;
        }

        if ($params['list_changefreq'] == -1)
        {
            $params['list_changefreq'] = $parent->changefreq;
        }


        $params['newsletter_priority'] = JArrayHelper::getValue($params, 'newsletter_priority', $parent->priority);
        $params['newsletter_changefreq'] = JArrayHelper::getValue($params, 'newsletter_changefreq', $parent->changefreq);

        if ($params['newsletter_priority'] == -1)
        {
            $params['newsletter_priority'] = $parent->priority;
        }

        if ($params['newsletter_changefreq'] == -1)
        {
            $params['newsletter_changefreq'] = $parent->changefreq;
        }

        switch ($uri->getVar('view'))
        {
            case 'lists':
                self::getListsTree($xmap, $parent, $params);
                break;

            case 'archive':
                $item = JFactory::getApplication()->getMenu()->getItem($parent->id);
                self::getNewsletter($xmap, $parent, $params, $item->params->get('listid'));
                break;
        }
    }

    /**
     * @param XmapDisplayerInterface $xmap
     * @param stdClass $parent
     * @param array $params
     */
    private static function getListsTree($xmap, stdClass $parent, array &$params)
    {
        $db = JFactory::getDbo();

        $query = $db->getQuery(true)
            ->select(array('listid', 'name'))
            ->from('#__acymailing_list AS l')
            ->where('l.published = 1')
            ->where('l.type = ' . $db->Quote('list'))
            ->order('l.ordering');

        if (!$params['show_unauth'])
        {
            $query->where('l.visible = 1');
        }

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        if (empty($rows))
        {
            return;
        }

        $xmap->changeLevel(1);

        foreach ($rows as $row)
        {
            $node = new stdclass;
            $node->id = $parent->id;
            $node->name = $row->name;
            $node->uid = $parent->uid . '_lid_' . $row->listid;
            $node->browserNav = $parent->browserNav;
            $node->priority = $params['list_priority'];
            $node->changefreq = $params['list_changefreq'];
            $node->link = 'index.php?option=com_acymailing&ctrl=archive&listid=' . $row->listid . '&Itemid=' . $parent->id;

            if ($xmap->printNode($node) !== false && $params['include_newsletter'])
            {
                self::getNewsletter($xmap, $parent, $params, $row->listid);
            }
        }

        $xmap->changeLevel(-1);
    }

    /**
     * @param XmapDisplayerInterface $xmap
     * @param stdClass $parent
     * @param array $params
     * @param int $listid
     */
    private static function getNewsletter($xmap, stdClass $parent, array &$params, $listid)
    {
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

        if (!$params['show_unauth'])
        {
            $query->where('l.visible = 1');
            $query->where('m.visible = 1');
        }

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        if (empty($rows))
        {
            return;
        }

        $xmap->changeLevel(1);

        foreach ($rows as $row)
        {
            $node = new stdclass;
            $node->id = $parent->id;
            $node->name = $row->subject;
            $node->uid = $parent->uid . '_' . $row->mailid;
            $node->browserNav = $parent->browserNav;
            $node->priority = $params['newsletter_priority'];
            $node->changefreq = $params['newsletter_changefreq'];
            $node->link = 'index.php?option=com_acymailing&ctrl=archive&task=view&listid=' . $row->listid . '&mailid=' . $row->mailid . '&Itemid=' . $parent->id;

            $xmap->printNode($node);
        }

        $xmap->changeLevel(-1);
    }
}