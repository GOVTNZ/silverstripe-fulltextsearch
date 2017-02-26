<?php

/**
 * Index ReindexRecordFilter
 * This class allows you to modify the query created by SolrReindexBase.
 */
interface ReindexQueryFilter {

	/**
	 * @param SearchIndex $indexInstance Current index to process
	 * @param $class Current class to process
	 * @param $query The query created by SolrReindexBase
	 */
	function filter(SearchIndex $indexInstance, $class, $query);

}