<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Run log for the exchange-rate cron (exchange_rate_run_log).
 *
 * One row per Cron::fetchExchangeRates execution — the audit trail of when it
 * ran, whether it succeeded, the headline MYR->USD rate, and how many
 * /Costing/Currency rates it touched. The rates themselves live in
 * costing_exchange_rates; this model only owns the trace.
 */
class Exchange_Rate_Model extends CI_Model
{
    /**
     * Open a run-log row (status = running) and return its run_id.
     *
     * @param array $data optional: source ('cli'|'web')
     * @return string run_id
     */
    function Start_Run_Log($data = array())
    {
        $run_id = 'exrate_' . date('YmdHis') . '_' . substr(md5(uniqid('', true)), 0, 8);
        $this->db->insert('exchange_rate_run_log', array(
            'run_id'     => $run_id,
            'source'     => isset($data['source']) ? $data['source'] : null,
            'status'     => 'running',
            'started_at' => date('Y-m-d H:i:s'),
        ));
        return $run_id;
    }

    /**
     * Close a run-log row with an outcome. Only whitelisted fields are written.
     *
     * @param string $run_id  from Start_Run_Log()
     * @param string $status  'completed' | 'failed'
     * @param array  $data    base_code, quote_code, rate, rate_date,
     *                        currencies_updated, currencies_skipped,
     *                        updated_codes, skipped_codes, error
     * @return void
     */
    function Finish_Run_Log($run_id, $status, $data = array())
    {
        $run_id = trim((string) $run_id);
        if ($run_id === '') {
            return;
        }

        $allowed = array(
            'base_code', 'quote_code', 'rate', 'rate_date',
            'currencies_updated', 'currencies_skipped',
            'updated_codes', 'skipped_codes', 'error',
        );
        $row = array_merge(
            array('status' => $status, 'finished_at' => date('Y-m-d H:i:s')),
            array_intersect_key($data, array_flip($allowed))
        );

        $this->db->where('run_id', $run_id)->update('exchange_rate_run_log', $row);
    }

    /**
     * The most recent successful run, or null. Used by the failure path to
     * report the last good rate that's still in effect.
     *
     * @return array|null
     */
    function Read_Last_Completed_Run()
    {
        $this->db->where('status', 'completed');
        $this->db->where('rate IS NOT NULL', null, false);
        $this->db->order_by('finished_at', 'DESC');
        $this->db->order_by('id', 'DESC');
        $this->db->limit(1);
        return $this->db->get('exchange_rate_run_log')->row_array();
    }
}
