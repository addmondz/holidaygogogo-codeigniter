<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/Competitor_Analysis_Model.php';

/**
 * Our_Product_Model — the "Our Product" tool's slice of the shared
 * competitor_analyses table. Identical persistence to Competitor_Analysis_Model;
 * the only difference is the feature scope, so its runs are stored and listed
 * independently from the Competitor Analysis history (feature = 'our_product').
 */
class Our_Product_Model extends Competitor_Analysis_Model
{
	protected $feature = 'our_product';
}
