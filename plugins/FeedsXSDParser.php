<?php
/**
 * Created by PhpStorm.
 * User: clemens
 * Date: 02-04-14
 * Time: 15:51
 */

//namespace Drupal\feeds_xsd_xml;

//use FeedsFetcherResult;
//use FeedsSource;

class FeedsXSDParser extends \FeedsParser
{

    /**
     * Implements FeedsParser::parse().
     */
    public function parse(FeedsSource $source, FeedsFetcherResult $fetcher_result) {
        return;
//        return new FeedsParserResult($rows, $source->feed_nid);
    }

    /**
     * Define defaults.
     */
    public function sourceDefaults() {
        return array(
            'delimiter' => $this->config['delimiter'],
            'no_headers' => $this->config['no_headers'],
        );
    }

    /**
     * Source form.
     *
     * Show mapping configuration as a guidance for import form users.
     */
    public function sourceForm($source_config) {
        $form = array();
        $form['#weight'] = -10;

        $output = t('Import !csv_files with one or more of these columns: !columns.', array('!csv_files' => l(t('CSV files'), 'http://en.wikipedia.org/wiki/Comma-separated_values'), '!columns' => implode(', ', $sources)));
        $items = array();
        $items[] = l(t('Download a template'), 'import/' . $this->id . '/template');
        $form['help'] = array(
            '#prefix' => '<div class="help">',
            '#suffix' => '</div>',
            'description' => array(
                '#prefix' => '<p>',
                '#markup' => $output,
                '#suffix' => '</p>',
            ),
            'list' => array(
                '#theme' => 'item_list',
                '#items' => $items,
            ),
        );
        $form['delimiter'] = array(
            '#type' => 'select',
            '#title' => t('Delimiter'),
            '#description' => t('The character that delimits fields in the CSV file.'),
            '#options'  => array(
                ',' => ',',
                ';' => ';',
                'TAB' => 'TAB',
                '|' => '|',
                '+' => '+',
            ),
            '#default_value' => isset($source_config['delimiter']) ? $source_config['delimiter'] : ',',
        );
        $form['no_headers'] = array(
            '#type' => 'checkbox',
            '#title' => t('No Headers'),
            '#description' => t('Check if the imported CSV file does not start with a header row. If checked, mapping sources must be named \'0\', \'1\', \'2\' etc.'),
            '#default_value' => isset($source_config['no_headers']) ? $source_config['no_headers'] : 0,
        );
        return $form;
    }

    /**
     * Define default configuration.
     */
    public function configDefaults() {
        return array(
            'delimiter' => ',',
            'no_headers' => 0,
        );
    }

    /**
     * Build configuration form.
     */
    public function configForm(&$form_state) {
        $form = array();
        $form['delimiter'] = array(
            '#type' => 'select',
            '#title' => t('Default delimiter'),
            '#description' => t('Default field delimiter.'),
            '#options' => array(
                ',' => ',',
                ';' => ';',
                'TAB' => 'TAB',
                '|' => '|',
                '+' => '+',
            ),
            '#default_value' => $this->config['delimiter'],
        );
        $form['no_headers'] = array(
            '#type' => 'checkbox',
            '#title' => t('No headers'),
            '#description' => t('Check if the imported CSV file does not start with a header row. If checked, mapping sources must be named \'0\', \'1\', \'2\' etc.'),
            '#default_value' => $this->config['no_headers'],
        );
        return $form;
    }

}