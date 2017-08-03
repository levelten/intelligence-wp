<?php
/**
 * @file
 * @author  Tom McCracken <tomm@levelten.net>
 * @version 1.0
 * @copyright 2013 LevelTen Ventures
 * 
 * @section LICENSE
 * All rights reserved. Do not use without permission.
 * 
 */
namespace LevelTen\Intel;
require_once 'class.list_report_view.php';

class ContentListReportView extends ListReportView {
  private $tableRowCount = 10;
  
  function __construct() {
    parent::__construct();
  }

  function renderReport() {
    $this->setParam('headerLabel', 'Pages');
    $this->setParam('indexBy', 'content');
    $this->setParam('indexByLabel', 'Path');
    if ($this->params['context'] == 'event') {
      $this->params['context'] = 'page';
    }
    return parent::renderReport();
  }
  
  function renderReport_bak() {
    $output = '';
    $startDate = $this->dateRange['start'];
    $endDate = $this->dateRange['end'];
    $reportModes = $this->modes;
    $targets = $this->targets;

    $isComp = 0;

    // set formaters and data sources for standard & trend reports
    $dataSource = 'content';
    $metricSource = 'content';

    $entranceFormat = '#,###';
    $pageviewFormat = '#,###';
    $valueFormat = '#,###.##';
    $entranceDeltaFormat = '+#,###.#';
    $pageviewDeltaFormat = '+#,###.#';
    $valueDeltaFormat = '+#,###.##';

    if ($reportModes[1] == 'trend') {
      $isComp = 1;
      $dataSource = 'content_delta';
      $metricSource = 'content_comp';
    }

    $init = ($isComp) ? 'entrances_pageview_value_indicators_deltas' : 'entrances_pageview_value_indicators';
    $table = new TableChart($init);
    $table->setColumnElement(0, 'label', 'Path');

    if ($reportModes[0] == 'seo') {
      $table->updateColumn(2, 'string', 'Top keyword');
    }
    else if ($reportModes[0] == 'social') {
      $table->updateColumn(2, 'string', 'Top referrer');
    }
    else  if ($reportModes[0] == 'engagement') {
      $table->setColumnElement(3, 'label', 'Event value');
      $table->updateColumn(4, 'number', 'Shares');
      $table->updateColumn(5, 'number', 'Clickbacks');
      $table->updateColumn(6, 'number', 'Comments');
      $table->insertColumn(7, 'number', 'CTA clicks');
    }

    $entrs_pie_chart = new PieChart();
    $entrs_pie_chart->addColumn('string', 'Page');
    if ($reportModes[1] != 'trend') {
      $entrs_pie_chart->setSetting('useTotal', 1);
    }

    if ($reportModes[0] == 'seo') {
      $entrs_pie_chart->setOption('title', 'Search referrers');
      $entrs_pie_chart->addColumn('number', 'Search referrers');
      $entrs_pie_chart->setSetting('total', $this->data[$dataSource]['_all']['organicSearch']['entrance']['entrances']);
    }
    else if ($reportModes[0] == 'social') {
      $entrs_pie_chart->setOption('title', 'Social referrers');
      $entrs_pie_chart->addColumn('number', 'Social referrers');
      $entrs_pie_chart->setSetting('total', $this->data[$dataSource]['_all']['socialNetwork']['entrance']['entrances']);
    }
    else if ($reportModes[0] == 'engagement') {
      $entrs_pie_chart->setOption('title', 'Pageviews');
      $entrs_pie_chart->addColumn('number', 'Pageviews');
      $entrs_pie_chart->setSetting('total', $this->data[$dataSource]['_totals']['pageview']['pageviews']);
    }
    else {
      $entrs_pie_chart->setOption('title', 'Entrances');
      $entrs_pie_chart->addColumn('number', 'Entrances');
      $entrs_pie_chart->setSetting('total', $this->data[$dataSource]['_totals']['entrance']['entrances']);
    }

    $value_pie_chart = new PieChart();
    $value_pie_chart->setOption('title', 'Value');
    $value_pie_chart->addColumn('string', 'Page');
    $value_pie_chart->addColumn('number', 'Entrances');
    if ($reportModes[1] != 'trend') {
      $value_pie_chart->setSetting('useTotal', 1);
    }
    $total = !empty($this->data[$dataSource]['_totals']['score']) ? $this->data[$dataSource]['_totals']['score'] : $this->data[$dataSource]['_all']['score'];
    $value_pie_chart->setSetting('total', $total);

    $bubble_chart = new BubbleChart();
    $item = array(
      'type' => 'string',
      'label' => 'Page',
    );
    $bubble_chart->addColumn($item);

    if ($reportModes[0] == 'engagement') {
      $item = array(
        'type' => 'number',
        'label' => 'Value/view',
        'pattern' => '#,###.##',
      );
      $bubble_chart->addColumn($item);
      $item = array(
        'type' => 'number',
        'label' => 'View/day',
        'pattern' => '#,###.#',
      );
      $bubble_chart->addColumn($item);
      $hAxis = array(
        'title' => t('Value/pageview'),
        'format' => '#,###.##',
      );
      $bubble_chart->setOption('hAxis', $hAxis);
      $vAxis = array(
        'title' => t('Pageviews/day'),
        'format' => '#,###.#',
      );
      $bubble_chart->setOption('vAxis', $vAxis);
    }
    else {
      $item = array(
        'type' => 'number',
        'label' => 'Value/entr',
        'pattern' => '#,###.##',
      );
      $bubble_chart->addColumn($item);
      $item = array(
        'type' => 'number',
        'label' => 'Entr/day',
        'pattern' => '#,###.#',
      );
      $bubble_chart->addColumn($item);
      $hAxis = array(
        'title' => t('Value/entrance'),
        'format' => '#,###.##',
      );
      $bubble_chart->setOption('hAxis', $hAxis);
      $vAxis = array(
        'title' => t('Entrances/day'),
        'format' => '#,###.#',
      );
      $bubble_chart->setOption('vAxis', $vAxis);
    }

    $item = array(
      'type' => 'number',
      'label' => 'Value/day',
      'pattern' => '#,###.##',
    );
    $bubble_chart->addColumn($item);

    $chartArea = array(
      'left' => 50,
      'top' =>  30,
      'width' => "95%",
      'height' => 330,
    );
    $bubble_chart->setOption('chartArea', $chartArea);

    $colorAxis = array(
      'minValue' => $targets['value_per_page_per_day_warning'],
      'maxValue' => $targets['value_per_page_per_day'],
      'colors' => array(
        $this->chartColors[2],
        $this->chartColors[3],
        $this->chartColors[1],
      ),
    );
    $bubble_chart->setOption('colorAxis', $colorAxis);
    $bubble_chart->setSetting('div_height', '400px');

    $value_str = '';

    // data loop
    $this->sortData('by_score_then_entrances', $dataSource);

    foreach($this->data[$dataSource] AS $n => $d) {
      if (empty($d['i']) || (substr($d['i'], 0 , 1) == '_')) { continue; }

      // for trend reports, score is a delta and can be negative. Don't allow
      // in report
      if ($d['score'] < 0) {
        break;
      }

      //list($host, $path) = explode('/', $d['i'], 2);
      $a = explode('/', $d['i'], 2);
      if (count($a) == 2) {
        $host = $a[0];
        $path = $a[1];
      }
      else {
        $host = $a[0];
        $path = '';
      }
      $pageMeta = $this->getPageMeta($path);

      if ($pageMeta) {
        $d['score_dates'] = $this->getPageScoreDates($startDate, $endDate, $pageMeta->created);
        $intent = $pageMeta->intent;
      }
      else {
        $d['score_dates'] = $this->getPageScoreDates($startDate, $endDate, 0);
        $intent = '';
      }
      if ($intent == 'u') {
        continue;
      }
      if (($reportModes[0] == 'attraction') && ($intent != 't')) {
        continue;
      }

      $entrancesDelta = $entrances = !empty($d['entrance']['entrances']) ? $d['entrance']['entrances'] : 0;
      $pageviewsDelta = $pageviews = !empty($d['pageview']['pageviews']) ? $d['pageview']['pageviews'] : 0;
      $valueDelta = $value = $d['score'];
      if ($isComp) {
        $entrances = !empty($this->data[$metricSource][$n]['entrance']['entrances']) ? $this->data[$metricSource][$n]['entrance']['entrances'] : 0;
        $pageviews = !empty($this->data[$metricSource][$n]['pageview']['pageviews']) ? $this->data[$metricSource][$n]['pageview']['pageviews'] : 0;
        $value = !empty($this->data[$metricSource][$n]['score']) ? $this->data[$metricSource][$n]['score'] : 0;
      }
      $days = isset($d['score_dates']['days']) ? $d['score_dates']['days'] : 0;
      $pathstr = $this->formatRowString("/$path", 60);

      if ($reportModes[0] == 'seo') {
        $entrances = !empty($d['organicSearch']['_all']['entrance']['entrances']) ? $d['organicSearch']['_all']['entrance']['entrances'] : 0;
        if (!$entrances) {
          continue;
        }
        $top_ref = '';
        usort($d['searchKeyword'], array($this, 'usort_entrances'));
        foreach ($d['searchKeyword'] AS $sn) {
          if (!empty($sn['i']) && (substr($sn['i'], 0 ,1) != '_')) {
            $top_ref = $this->formatRowString($sn['i'], 40) . ' (' . $sn['entrance']['entrances'] . ')';
            break;
          }
        }
      }
      else if ($reportModes[0] == 'social') {
        //$events = $d['pageview']['events'];
        //$shares = !empty($events['Social share!']) ? $events['Social share!']['totalValuedEvents'] : 0;
        //$clickbacks = !empty($events['Social share clickback!']) ? $events['Social share clickback!']['totalValuedEvents'] : 0;
        $entrances = !empty($d['socialNetwork']['_all']['entrance']['entrances']) ? $d['socialNetwork']['_all']['entrance']['entrances'] : 0;
        if (!$entrances) {
          continue;
        }
        $top_ref = '';
        usort($d['socialNetwork'], array($this, 'usort_entrances'));
        foreach ($d['socialNetwork'] AS $sn) {
          if (!empty($sn['i']) && (substr($sn['i'], 0 ,1) != '_')) {
            $top_ref = $this->formatRowString($sn['i'], 40) . ' (' . $sn['entrance']['entrances'] . ')';
            break;
          }
        }
      }
      else if ($reportModes[0] == 'engagement') {
        $events = $d['pageview']['events'];
        $shares = !(empty($events['Social share!'])) ? $events['Social share!']['totalValuedEvents'] : 0;
        $clickbacks = !empty($events['Social share clickback!']) ? $events['Social share clickback!']['totalValuedEvents'] : 0;
        $comments = !(empty($events['Comment!'])) ? $events['Comment!']['totalValuedEvents'] : 0;
        $ctaClicks = !(empty($events['CTA click!'])) ? $events['CTA click!']['totalValuedEvents'] : 0;
        if (($shares + $comments + $ctaClicks) == 0) {
          continue;
        }
      }

      $table->newWorkingRow();
      // check if uri is absolute or relative. Add schema if absolute.
      $uri = ((substr($d['i'], 0, 1) != '/') ? 'http://' : '') . $d['i'];
      $l = render::link($pathstr, $uri, array('attributes' => array('target' => '_blank')));
      $table->addRowItem($l);

      $table->addRowItem($entrances, '', $entranceFormat);
      if ($isComp) {
        $table->addRowItem($entrancesDelta, '', $entranceDeltaFormat);
      }

      if ($reportModes[0] == 'seo') {
        $table->addRowItem($top_ref);
      }
      else if ($reportModes[0] == 'social') {
        $table->addRowItem($top_ref);
      }
      else {
        $table->addRowItem($pageviews, '', $pageviewFormat);
        if ($isComp) {
          $table->addRowItem($pageviewsDelta, '', $pageviewDeltaFormat);
        }
      }

      if ($reportModes[0] != 'engagement') {
        $table->addRowItem($value, '', $valueFormat);
        if ($isComp) {
          $table->addRowItem($valueDelta, '', $valueDeltaFormat);
        }
        $format = $this->renderValueScore(array('value' => $value, 'type' => 'value_per_page_per_day', 'number_of_days' => $days), $value_str);
        $table->addRowItem((float)$value_str, $format);

        if ($entrances) {
          $format = $this->renderValueScore(array('value' => $entrances, 'type' => 'entrances_per_page_per_day', 'number_of_days' => $days), $value_str);
          $table->addRowItem((float)$value_str, $format);

          $format = $this->renderValueScore(array('value' => ($value / $entrances), 'type' => 'value_per_page_per_entrance'), $value_str);
          $table->addRowItem((float)$value_str, $format);
        }
        else {
          $table->addRowItem(0);
          $table->addRowItem(0);
        }
      }

      if ($reportModes[0] == 'engagement') {
        $table->addRowItem($value);
        $table->addRowItem($shares);
        $table->addRowItem($clickbacks);
        $table->addRowItem($comments);
        $table->addRowItem($ctaClicks);
      }

      $table->addRowItem(implode(' ', $d['links']));
      $table->addRow();

      if ($reportModes[0] == 'engagement') {
        $entrs_pie_chart->addRow(array($pathstr, $pageviews));
      }
      else {
        $entrs_pie_chart->addRow(array($pathstr, $entrances));
      }

      $value_pie_chart->addRow(array($pathstr, $value));


      if ($reportModes[0] == 'engagement') {
        $vph = ($pageviews) ? $value/$pageviews : 0;
        $hpd = ($pageviews/$days);
        $bubble_chart->addRow(array($pathstr, round($vph, 2), round($hpd, 1), round($event_value/$days, 2)));
      }
      else {
        $vpe = ($entrances) ? $value/$entrances : 0;
        $epd = ($entrances/$days);
        $bubble_chart->addRow(array($pathstr, round($vpe, 2), round($epd, 1), round($value/$days, 2)));
      }

      if ($table->curRowCount >= $this->tableRowCount) {
        break;
      }
    }
//dsm($table);

    $bubble_chart->setSetting('plotThreshold', array('columnIndex' => 2, 'minValue' => 1));
    $output = '';
    $output .= '<div id="content-section" class="report-section">';
    $output .= '<div class="pane-left-40">';
    $output .= $value_pie_chart->renderOutput();
    $output .= $entrs_pie_chart->renderOutput();
    $output .= '</div>';
    $output .= '<div class="pane-spacer">&nbsp;</div>';
    $output .= '<div class="pane-right-60">';
    $output .= $bubble_chart->renderOutput();
    $output .= '</div>';
    $output .= '</div>';

    $output .= '<div id="content-section" class="report-section">';
    $output .= '<h3>' . t('Pages') . '</h3>';
    //$output .= $out_table;
    $output .= $table->renderOutput();
    $output .= '</div>';

    $output .= 'generated by <a href="http://levelten.net" target="_blank">LevelTen Intelligence</a>';

    return '<div id="intel-report">' . $output . '</div>';
  }
}