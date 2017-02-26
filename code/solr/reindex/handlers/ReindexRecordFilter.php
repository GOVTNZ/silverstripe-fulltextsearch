<?php

/**
 * Index ReindexRecordFilter
 * This class allows you to filter objects that would be part of the index that
 * is currently being processed.
 */
interface ReindexRecordFilter {

	/**
	 * @param $index Class name of the current index
	 * @param $item Object to process
	 * @return boolean Returns TRUE if this object ($item) should be excluded from the current index.
	 */
	function isRecordForThisIndex($index, $item);

}