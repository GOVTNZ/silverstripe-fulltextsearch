<?php

abstract class SearchUpdateProcessor {
	
	/**
	 * List of dirty records to process in format
	 * 
	 * array(
	 *   '$BaseClass' => array(
	 *     '$State Key' => array(
	 *       'state' => array(
	 *         'key1' => 'value',
	 *         'key2' => 'value'
	 *       ),
	 *       'ids' => array(
	 *         '*id*' => array(
	 *           '*Index Name 1*',
	 *           '*Index Name 2*'
	 *         )
	 *       )
	 *     )
	 *   )
	 * )
	 *
	 * @var array
	 */
	protected $dirty;
	
	public $recordFilter;

	static $dependencies = array(
		'recordFilter' => '%$RecordFilterService'
	);


	public function __construct() {
		$this->dirty = array();
	}

	public function addDirtyIDs($class, $statefulids, $index) {
		$base = ClassInfo::baseDataClass($class);
		$forclass = isset($this->dirty[$base]) ? $this->dirty[$base] : array();

		foreach ($statefulids as $statefulid) {
			$id = $statefulid['id'];
			$state = $statefulid['state']; $statekey = serialize($state);

			if (!isset($forclass[$statekey])) {
				$forclass[$statekey] = array('state' => $state, 'ids' => array($id => array($index)));
			}
			else if (!isset($forclass[$statekey]['ids'][$id])) {
				$forclass[$statekey]['ids'][$id] = array($index);
			}
			else if (array_search($index, $forclass[$statekey]['ids'][$id]) === false) {
				$forclass[$statekey]['ids'][$id][] = $index;
				// dirty count stays the same
			}
		}

		$this->dirty[$base] = $forclass;
	}
	
	/**
	 * Generates the list of indexes to process for the dirty items
	 * 
	 * @return array
	 */
	protected function prepareIndexes() {
		$originalState = SearchVariant::current_state();
		$dirtyIndexes = array();
		$dirty = $this->getSource();
		$indexes = FullTextSearch::get_indexes();
		foreach ($dirty as $base => $statefulids) {
			if (!$statefulids) continue;

			foreach ($statefulids as $statefulid) {
				$state = $statefulid['state'];
				$ids = $statefulid['ids'];

				SearchVariant::activate_state($state);

				// Ensure that indexes for all new / updated objects are included
				$objs = DataObject::get($base)->byIDs(array_keys($ids));
				foreach ($objs as $obj) {
					foreach ($ids[$obj->ID] as $index) {
						$exclude = $indexes[$index]->variantStateExcluded($state);

						// apply user filter to results if configured
						if (!$exclude && $this->recordFilter) {
							$exclude = !$this->recordFilter->isRecordForThisIndex($index, $obj);
						}

						if (!$exclude) {
							$indexes[$index]->add($obj);
							$dirtyIndexes[$index] = $indexes[$index];
						}
					}
					unset($ids[$obj->ID]);
				}

				// Generate list of records that do not exist and should be removed
				foreach ($ids as $id => $fromindexes) {
					foreach ($fromindexes as $index) {
						if (!$indexes[$index]->variantStateExcluded($state)) {
							$indexes[$index]->delete($base, $id, $state);
							$dirtyIndexes[$index] = $indexes[$index];
						}
					}
				}
			}
		}
		
		SearchVariant::activate_state($originalState);
		return $dirtyIndexes;
	}
	
	/**
	 * Commits the specified index to the Solr service
	 * 
	 * @param SolrIndex $index Index object
	 * @return bool Flag indicating success
	 */
	protected function commitIndex($index) {
		return $index->commit() !== false;
	}
	
	/**
	 * Gets the record data source to process
	 * 
	 * @return array
	 */
	protected function getSource() {
		return $this->dirty;
	}

	/**
	 * Process all indexes, returning true if successful
	 * 
	 * @return bool Flag indicating success
	 */
	public function process() {
		// Generate and commit all instances
		$indexes = $this->prepareIndexes();
		foreach ($indexes as $index) {
			if(!$this->commitIndex($index)) return false;
		}
		return true;
	}

	abstract public function triggerProcessing();
}
