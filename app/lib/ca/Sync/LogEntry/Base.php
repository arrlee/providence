<?php
/** ---------------------------------------------------------------------
 * app/lib/ca/Sync/LogEntry/Base.php
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2016 Whirl-i-Gig
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
 * @subpackage Sync
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 *
 * ----------------------------------------------------------------------
 */

namespace CA\Sync\LogEntry;

require_once(__CA_LIB_DIR__.'/ca/Sync/LogEntry/Attribute.php');
require_once(__CA_LIB_DIR__.'/ca/Sync/LogEntry/AttributeValue.php');
require_once(__CA_LIB_DIR__.'/ca/Sync/LogEntry/Bundlable.php');
require_once(__CA_LIB_DIR__.'/ca/Sync/LogEntry/Relationship.php');
require_once(__CA_LIB_DIR__.'/ca/Sync/LogEntry/Label.php');


abstract class Base {

	/**
	 * The log entry from the remote/source system
	 * @var array
	 */
	private $opa_log;

	/**
	 * The system id of the remote/source system
	 * @var string
	 */
	private $ops_source_system_id;

	/**
	 * Log id (on the remote/source system) this object represents
	 * @var int
	 */
	private $opn_log_id;

	/**
	 * @var \Datamodel
	 */
	private $opo_datamodel;

	/**
	 * @var \BaseModel
	 */
	private $opt_instance;

	/**
	 * Base constructor.
	 * @param string $ops_source_system_id
	 * @param int $opn_log_id
	 * @param array $opa_log
	 * @throws InvalidLogEntryException
	 */
	public function __construct($ops_source_system_id, $opn_log_id, array $opa_log) {
		if(!is_array($opa_log)) {
			throw new InvalidLogEntryException('Log entry must be array');
		}

		if(!isset($opa_log['log_id'])) {
			throw new InvalidLogEntryException('Log entry does not have an id');
		}

		if(!is_numeric($opn_log_id)) {
			throw new InvalidLogEntryException('Log id is not numeric');
		}
		;
		if(strlen($ops_source_system_id) != 36) {
			throw new InvalidLogEntryException('source system GUID is not a valid guid. String length was: '. strlen($ops_source_system_id));
		}

		$this->opa_log = $opa_log;
		$this->ops_source_system_id = $ops_source_system_id;
		$this->opn_log_id = $opn_log_id;

		$this->opo_datamodel = \Datamodel::load();

		$this->opt_instance = $this->getDatamodel()->getInstance($this->getTableNum());

		if(!($this->opt_instance instanceof \BaseModel)) {
			throw new InvalidLogEntryException('table num is invalid for this log entry');
		}

		// if this is not an insert log entry, load the specified row by GUID
		if(!$this->isInsert()) {
			if(!$this->getModelInstance()->loadByGUID($this->getGUID())) {
				throw new InvalidLogEntryException('mode was insert or update but the given GUID could not be found');
			}
		}
	}

	/**
	 * Applies this log entry to the local system.
	 * We don't dicate *how* implementations do this. It's advised to not cram all code into
	 * this function though. Breaking it out by insert/update/delete probably makes sense
	 *
	 * @return mixed
	 */
	abstract public function apply();

	/**
	 * @return array
	 */
	public function getLog() {
		return $this->opa_log;
	}

	/**
	 * @return string
	 */
	public function getSourceSystemID() {
		return $this->ops_source_system_id;
	}

	/**
	 * @return int
	 */
	public function getLogId() {
		return $this->opn_log_id;
	}

	/**
	 * Get record snapshot from log entry
	 * @return array|null
	 */
	public function getSnapshot() {
		if(isset($this->opa_log['snapshot']) && is_array($this->opa_log['snapshot'])) {
			return $this->opa_log['snapshot'];
		}

		return null;
	}

	/**
	 * Get a specific entry (e.g. 'type_id') from the log snapshot
	 * @param string $ps_entry the field name
	 * @return mixed|null
	 */
	public function getSnapshotEntry($ps_entry) {
		if($va_snapshot = $this->getSnapshot()) {
			if(isset($va_snapshot[$ps_entry])) {
				return $ps_entry;
			}
		}

		return null;
	}

	/**
	 * @return null|int
	 */
	public function getTableNum() {
		return isset($this->opa_log['logged_table_num']) ? $this->opa_log['logged_table_num'] : null;
	}

	/**
	 * @return null|int
	 */
	public function getRowID() {
		return isset($this->opa_log['logged_row_id']) ? $this->opa_log['logged_row_id'] : null;
	}

	/**
	 * @return null|array
	 */
	public function getSubjects() {
		return isset($this->opa_log['subjects']) ? $this->opa_log['subjects'] : null;
	}

	/**
	 * @return null|string
	 */
	public function getGUID() {
		return isset($this->opa_log['guid']) ? $this->opa_log['guid'] : null;
	}

	/**
	 * @return bool
	 */
	public function isInsert() {
		return (isset($this->opa_log['changetype']) && ($this->opa_log['changetype'] == 'I'));
	}

	/**
	 * @return bool
	 */
	public function isUpdate() {
		return (isset($this->opa_log['changetype']) && ($this->opa_log['changetype'] == 'U'));
	}

	/**
	 * @return bool
	 */
	public function isDelete() {
		return (isset($this->opa_log['changetype']) && ($this->opa_log['changetype'] == 'D'));
	}

	/**
	 * Get Datamodel
	 * @return \Datamodel
	 */
	public function getDatamodel() {
		return $this->opo_datamodel;
	}

	/**
	 * Get model instance for row referenced in change log entry
	 * @return \BaseModel|null
	 */
	public function getModelInstance() {
		return $this->opt_instance;
	}

	public function setIntrinsicsFromSnapshotInModelInstance() {
		$this->getModelInstance()->setMode(ACCESS_WRITE);

		foreach($this->getSnapshot() as $vs_field => $vm_val) {
			if($this->getModelInstance()->hasField($vs_field)) {
				// @todo have to skip more fields here ... parent_id, hierarchy indexes, various *_id fields
				if($this->getModelInstance()->primaryKey() == $vs_field) { continue; }

				$this->getModelInstance()->set($vs_field, $vm_val);
			}
		}
	}

	/**
	 * @param string $ps_source_system_id
	 * @param int $pn_log_id
	 * @param array $pa_log
	 * @return \CA\Sync\LogEntry\Base
	 * @throws InvalidLogEntryException
	 */
	public static function getInstance($ps_source_system_id, $pn_log_id, $pa_log) {
		if(!is_array($pa_log) || !isset($pa_log['logged_table_num'])) {
			throw new InvalidLogEntryException('Invalid log entry');
		}

		$o_dm = \Datamodel::load();

		$t_instance = $o_dm->getInstance($pa_log['logged_table_num']);

		if($t_instance instanceof \BaseRelationshipModel) {
			return new Relationship($ps_source_system_id, $pn_log_id, $pa_log);
		} elseif($t_instance instanceof \ca_attributes) {
			return new Attribute($ps_source_system_id, $pn_log_id, $pa_log);
		} elseif($t_instance instanceof \ca_attribute_values) {
			return new AttributeValue($ps_source_system_id, $pn_log_id, $pa_log);
		} elseif($t_instance instanceof \BaseLabel) {
			return new Label($ps_source_system_id, $pn_log_id, $pa_log);
		} elseif($t_instance instanceof \BundlableLabelableBaseModelWithAttributes) {
			return new Bundlable($ps_source_system_id, $pn_log_id, $pa_log);
		}

		throw new InvalidLogEntryException('Invalid table in log entry');
	}

}

class InvalidLogEntryException extends \Exception {}
