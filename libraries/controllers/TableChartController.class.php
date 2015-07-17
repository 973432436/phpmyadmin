<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */

/**
 * Holds the PMA\TableChartController
 *
 * @package PMA
 */

namespace PMA\Controllers\Table;

use PMA\DI\Container;
use PMA_Util;
use PMA_Message;
use PMA\Template;
use PMA\Controllers\TableController;

require_once 'libraries/common.inc.php';
require_once 'libraries/Util.class.php';
require_once 'libraries/Message.class.php';
require_once 'libraries/Template.class.php';
require_once 'libraries/controllers/TableController.class.php';

/**
 * Handles table related logic
 *
 * @package PhpMyAdmin
 */
class TableChartController extends TableController
{

    /**
     * @var string $sql_query
     */
    protected $sql_query;

    /**
     * @var $url_query
     */
    protected $url_query;

    /**
     * @var array $cfg
     */
    protected $cfg;

    function __construct($sql_query, $url_query, $cfg)
    {
        parent::__construct();

        $this->sql_query = $sql_query;
        $this->url_query = $url_query;
        $this->cfg = $cfg;
    }

    /*
     * Execute the query and return the result
     */
    public function indexAction()
    {
        if (isset($_REQUEST['ajax_request'])
            && isset($_REQUEST['pos'])
            && isset($_REQUEST['session_max_rows'])
        ) {
            $this->ajaxAction();
            return;
        }

        // Throw error if no sql query is set
        if (!isset($this->sql_query) || $this->sql_query == '') {
            $this->response->isSuccess(false);
            $this->response->addHTML(
                PMA_Message::error(__('No SQL query was set to fetch data.'))
            );
            return;
        }

        $this->response->getHeader()->getScripts()->addFiles(array(
            'chart.js',
            'tbl_chart.js',
            'jqplot/jquery.jqplot.js',
            'jqplot/plugins/jqplot.barRenderer.js',
            'jqplot/plugins/jqplot.canvasAxisLabelRenderer.js',
            'jqplot/plugins/jqplot.canvasTextRenderer.js',
            'jqplot/plugins/jqplot.categoryAxisRenderer.js',
            'jqplot/plugins/jqplot.dateAxisRenderer.js',
            'jqplot/plugins/jqplot.pointLabels.js',
            'jqplot/plugins/jqplot.pieRenderer.js',
            'jqplot/plugins/jqplot.highlighter.js'
        ));

        /**
         * Extract values for common work
         * @todo Extract common files
         */
        $db = &$this->db;
        $table = &$this->table;

        /**
         * Runs common work
         */
        if (/*overload*/ mb_strlen($this->table)) {
            $url_params['goto'] = PMA_Util::getScriptNameForOption(
                $this->cfg['DefaultTabTable'], 'table'
            );
            $url_params['back'] = 'tbl_sql.php';
            include 'libraries/tbl_common.inc.php';
            include 'libraries/tbl_info.inc.php';
        } elseif (/*overload*/ mb_strlen($this->db)) {
            $url_params['goto'] = PMA_Util::getScriptNameForOption(
                $this->cfg['DefaultTabDatabase'], 'database'
            );
            $url_params['back'] = 'sql.php';
            include 'libraries/db_common.inc.php';
            include 'libraries/db_info.inc.php';
        } else {
            $url_params['goto'] = PMA_Util::getScriptNameForOption(
                $this->cfg['DefaultTabServer'], 'server'
            );
            $url_params['back'] = 'sql.php';
            include 'libraries/server_common.inc.php';
        }

        $data = array();

        $result = $this->dbi->tryQuery($this->sql_query);
        $fields_meta = $this->dbi->getFieldsMeta($result);
        while ($row = $this->dbi->fetchAssoc($result)) {
            $data[] = $row;
        }

        $keys = array_keys($data[0]);

        $numeric_types = array('int', 'real');
        $numeric_column_count = 0;
        foreach ($keys as $idx => $key) {
            if (in_array($fields_meta[$idx]->type, $numeric_types)) {
                $numeric_column_count++;
            }
        }

        if ($numeric_column_count == 0) {
            $this->response->isSuccess(false);
            $this->response->addJSON(
                'message',
                __('No numeric columns present in the table to plot.')
            );
            return;
        }

        $url_params['db'] = $this->db;
        $url_params['reload'] = 1;

        /**
         * Displays the page
         */
        $this->response->addHTML(Template::get('tbl_chart')
            ->render(array(
                'url_query' => $this->url_query,
                'url_params' => $url_params,
                'keys' => $keys,
                'fields_meta' => $fields_meta,
                'numeric_types' => $numeric_types,
                'numeric_column_count' => $numeric_column_count,
                'sql_query' => $this->sql_query
            )));
    }

    /**
     * Handle ajax request
     */
    public function ajaxAction()
    {
        /**
         * Extract values for common work
         * @todo Extract common files
         */
        $db = &$this->db;
        $table = &$this->table;

        $tableLength = /*overload*/
            mb_strlen($this->table);
        $dbLength = /*overload*/
            mb_strlen($this->db);
        if ($tableLength && $dbLength) {
            include './libraries/tbl_common.inc.php';
        }

        $sql_with_limit = sprintf(
            'SELECT * FROM(%s) AS `temp_res` LIMIT %s, %s',
            $this->sql_query,
            $_REQUEST['pos'],
            $_REQUEST['session_max_rows']
        );
        $data = array();
        $result = $this->dbi->tryQuery($sql_with_limit);
        while ($row = $this->dbi->fetchAssoc($result)) {
            $data[] = $row;
        }

        if (empty($data)) {
            $this->response->isSuccess(false);
            $this->response->addJSON('message', __('No data to display'));
            return;
        }
        $sanitized_data = array();

        foreach ($data as $data_row_number => $data_row) {
            $tmp_row = array();
            foreach ($data_row as $data_column => $data_value) {
                $tmp_row[htmlspecialchars($data_column)] = htmlspecialchars($data_value);
            }
            $sanitized_data[] = $tmp_row;
        }
        $this->response->isSuccess(true);
        $this->response->addJSON('message', null);
        $this->response->addJSON('chartData', json_encode($sanitized_data));
    }
}
