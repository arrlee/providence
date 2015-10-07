<?php
/** ---------------------------------------------------------------------
 * app/lib/ca/Service/SimpleService.php
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2015 Whirl-i-Gig
 *
 * For more information visit http://www.CollectiveAccess.org
 *
 * This program is free software; you may redistribute it and/or modify it under
 * the terms of the provided license as published by Whirl-i-Gig
 *
 * CollectiveAccess is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTIES whatsoever, including any implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * This source code is free and modifiable under the terms of
 * GNU General Public License. (http://www.gnu.org/copyleft/gpl.html). See
 * the "license.txt" file for details, or visit the CollectiveAccess web site at
 * http://www.CollectiveAccess.org
 *
 * @package CollectiveAccess
 * @subpackage WebServices
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 *
 * ----------------------------------------------------------------------
 */

class SimpleService {
	# -------------------------------------------------------
	/**
	 * Dispatch service call
	 * @param string $ps_endpoint
	 * @param RequestHTTP $po_request
	 * @return array
	 * @throws Exception
	 */
	public static function dispatch($ps_endpoint, $po_request) {

		$va_endpoint_config = self::getEndpointConfig($ps_endpoint); // throws exception if it can't be found

		switch($va_endpoint_config['type']) {
			case 'search':
				return self::runSearchEndpoint($va_endpoint_config, $po_request);
			case 'detail':
			default:
				return self::runDetailEndpoint($va_endpoint_config, $po_request);
		}
	}
	# -------------------------------------------------------
	/**
	 * @param array $pa_config
	 * @param RequestHTTP $po_request
	 * @return array
	 * @throws Exception
	 */
	private static function runDetailEndpoint($pa_config, $po_request) {
		$o_dm = Datamodel::load();

		// load instance
		$t_instance = $o_dm->getInstance($pa_config['table']);
		if(!($t_instance instanceof BundlableLabelableBaseModelWithAttributes)) {
			throw new Exception('invalid table');
		}

		$pm_id = $po_request->getParameter('id', pString);
		if(!$t_instance->load($pm_id)) {
			$t_instance->load(array($t_instance->getProperty('ID_NUMBERING_ID_FIELD') => $pm_id));
		}

		if(!$t_instance->getPrimaryKey()) {
			throw new Exception('Could not load record');
		}

		// restrictToTypes
		if($pa_config['restrictToTypes'] && is_array($pa_config['restrictToTypes']) && (sizeof($pa_config['restrictToTypes']) > 0)) {
			if(!in_array($t_instance->getTypeCode(), $pa_config['restrictToTypes'])) {
				throw new Exception('Invalid parameters');
			}
		}

		$va_return = array();
		foreach($pa_config['content'] as $vs_key => $vs_template) {
			$va_return[self::sanitizeKey($vs_key)] = $t_instance->getWithTemplate($vs_template);
		}

		return $va_return;
	}
	# -------------------------------------------------------
	/**
	 * @param array $pa_config
	 * @param RequestHTTP $po_request
	 * @return array
	 * @throws Exception
	 */
	private static function runSearchEndpoint($pa_config, $po_request) {
		$o_dm = Datamodel::load();

		// load blank instance
		$t_instance = $o_dm->getInstance($pa_config['table']);
		if(!($t_instance instanceof BundlableLabelableBaseModelWithAttributes)) {
			throw new Exception('invalid table');
		}

		if(!($ps_q = $po_request->getParameter('q', pString))) {
			throw new Exception('No query specified');
		}
		
		if (($pn_limit = $po_request->getParameter('limit', pInteger)) < 1) {
			$pn_limit = 0;
		}
		if (($pn_start = $po_request->getParameter('start', pInteger)) < 1) {
			$pn_start = 0;
		}
		
		$ps_sort = (isset($pa_config['sort']) && $pa_config['sort']) ? $pa_config['sort'] : $po_request->getParameter('sort', pString);
		$ps_sort_direction = (isset($pa_config['sortDirection']) && $pa_config['sortDirection']) ? $pa_config['sortDirection'] : $po_request->getParameter('sortDirection', pString);

		$o_search = caGetSearchInstance($pa_config['table']);

		// restrictToTypes
		if($pa_config['restrictToTypes'] && is_array($pa_config['restrictToTypes']) && (sizeof($pa_config['restrictToTypes']) > 0)) {
			$o_search->setTypeRestrictions($pa_config['restrictToTypes']);
		}

		$o_res = $o_search->search($ps_q, ['limit' => $ps_sort ? 0 : $pn_limit, 'sort' => $ps_sort, 'sortDirection' => $ps_sort_direction]);
		if ($pn_start > 0) { $o_res->seek($pn_start); }

		$va_return = array();
		$vn_c = 0;
		
		// TODO: why is template prefetching making things *SLOWER*?
		$o_res->disableGetWithTemplatePrefetch(true);
		
		while($o_res->nextHit()) {
			$va_hit = array();

			foreach($pa_config['content'] as $vs_key => $vs_template) {
				$va_hit[self::sanitizeKey($vs_key)] = $o_res->getWithTemplate($vs_template);
			}

			$va_return['data'][] = $va_hit;
			
			$vn_c++;
			
			if ($vn_c == $pn_limit) { break; }
		}

		return $va_return;

	}
	# -------------------------------------------------------
	/**
	 * Get configuration for endpoint. Also does config validation.
	 * @param string $ps_endpoint
	 * @return array
	 * @throws Exception
	 */
	private static function getEndpointConfig($ps_endpoint) {
		$o_app_conf = Configuration::load();
		$o_service_conf = Configuration::load($o_app_conf->get('services_config'));

		$va_endpoints = $o_service_conf->get('simple_api_endpoints');

		if(!is_array($va_endpoints) || !isset($va_endpoints[$ps_endpoint]) || !is_array($va_endpoints[$ps_endpoint])) {
			throw new Exception('Invalid service endpoint');
		}

		if(!isset($va_endpoints[$ps_endpoint]['type']) || !in_array($va_endpoints[$ps_endpoint]['type'], array('search', 'detail'))) {
			throw new Exception('Service endpoint config is invalid: type must be search or detail');
		}

		if(!isset($va_endpoints[$ps_endpoint]['content']) || !is_array($va_endpoints[$ps_endpoint]['content'])) {
			throw new Exception('Service endpoint config is invalid: No display content defined');
		}

		return $va_endpoints[$ps_endpoint];
	}
	# -------------------------------------------------------
	private static function sanitizeKey($ps_key) {
		return preg_replace('[^A-Za-z0-9\-\_\.\:]', '', $ps_key);
	}
	# -------------------------------------------------------
}