<?php
/**
 * @version: $Id: inbox.php 1213 2011-04-16 19:28:28Z Radek Suski $
 * @package: SobiPro Library
 * ===================================================
 * @author
 * Name: Sigrid Suski & Radek Suski, Sigsiu.NET GmbH
 * Email: sobi[at]sigsiu.net
 * Url: http://www.Sigsiu.NET
 * ===================================================
 * @copyright Copyright (C) 2006 - 2011 Sigsiu.NET GmbH (http://www.sigsiu.net). All rights reserved.
 * @license see http://www.gnu.org/licenses/lgpl.html GNU/LGPL Version 3.
 * You can use, redistribute this file and/or modify it under the terms of the GNU Lesser General Public License version 3
 * ===================================================
 * $Date: 2011-04-16 21:28:28 +0200 (Sat, 16 Apr 2011) $
 * $Revision: 1213 $
 * $Author: Radek Suski $
 * File location: components/com_sobipro/opt/fields/inbox.php $
 */
defined( 'SOBIPRO' ) || exit( 'Restricted access' );

SPLoader::loadClass( 'opt.fields.date' );

/**
 * @author Radek Suski
 * @version 1.0
 * @created 09-Sep-2009 12:52:45 PM
 */
class SPField_Date extends SPFieldType implements SPFieldInterface
{
	/**
	 * @var int
	 */
	protected $maxLength =  150;
	/**
	 * @var int
	 */
	protected $width =  350;
	/**
	 * @var string
	 */
	protected $cssClass = "";
	/**
	 * @var string
	 */
	protected $searchRangeValues = "";
	/**
	 * @var string
	 */
	protected $searchMethod = 'general';

	/**
	 * Shows the field in the edit entry or add entry form
	 * @param bool $return return or display directly
	 * @return string
	 */
	public function field( $return = false )
	{
		if( !( $this->enabled ) ) {
			return false;
		}
		
		$class =  $this->required ? $this->cssClass.' required' : $this->cssClass;
		$params = array( 'id' => $this->nid, 'size' => $this->width, 'class' => $class );
		$document =& JFactory::getDocument();
		$script='jQuery(function() 
			{ jQuery( "#'.$params[id].'" ).datepicker(
			{
			showOn: "both",
			});
			});
			';
		$document->addScriptDeclaration($script);		
		if( $this->maxLength ) {
			$params[ 'maxlength' ] = $this->maxLength;
		}
		if( $this->width ) {
			$params[ 'style' ] = "width: {$this->width}px;";
		}
		$field = SPHtml_Input::text( $this->nid, $this->getRaw(), $params );
		if( !$return ) {
			echo $field;
		}
		else {
			return $field.$script;
		}
	}

	/**
	 * Returns the parameter list
	 * @return array
	 */
	protected function getAttr()
	{
		return array( 'maxLength', 'width', 'searchMethod', 'searchRangeValues' );
	}

	/**
	 * Gets the data for a field, verify it and pre-save it.
	 * @param SPEntry $entry
	 * @param string $tsid
	 * @param string $request
	 * @return void
	 */
	public function submit( &$entry, $tsid = null, $request = 'POST' )
	{
		$data = $this->verify( $entry, $request );
		if( strlen( $data ) ) {
			return SPRequest::search( $this->nid, $request );
		}
		else {
			return array();
		}
	}

	/**
	 * @param SPEntry $entry
	 * @param string $request
	 * @return string
	 */
	private function verify( $entry, $request )
	{
		$data = SPRequest::raw( $this->nid, null, $request );
		$dexs = strlen( $data );
		/* check if it was required */
		if( $this->required && !( $dexs ) ) {
			throw new SPException( SPLang::e( 'FIELD_REQUIRED_ERR', $this->name ) );
		}
		/* check if there was a filter */
		if( $this->filter && $dexs ) {
			$registry =& SPFactory::registry();
			$registry->loadDBSection( 'fields_filter' );
			$filters = $registry->get( 'fields_filter' );
			$filter = isset( $filters[ $this->filter ] ) ? $filters[ $this->filter ] : null;
			if( !( count( $filter ) ) ) {
				throw new SPException( SPLang::e( 'FIELD_FILTER_ERR', $this->filter ) );
			}
			else {
				if( !( preg_match( base64_decode( $filter[ 'params' ] ), $data ) ) ) {
					throw new SPException( str_replace( '$field', $this->name, SPLang::e( $filter[ 'description' ] ) ) );
				}
			}
		}
		/* check if there was an adminField */
		if( $this->adminField && $dexs ) {
			if( !( Sobi:: Can( 'entry.adm_fields.edit' ) ) ) {
				throw new SPException( SPLang::e( 'FIELD_NOT_AUTH', $this->name ) );
			}
		}
		/* check if it was free */
		if( !( $this->isFree ) && $this->fee && $dexs ) {
			SPFactory::payment()->add( $this->fee, $this->name, $entry->get( 'id' ), $this->fid );
		}
		/* check if it should contains unique data */
		if( $this->uniqueData && $dexs ) {
			$matches = $this->searchData( $data, Sobi::Reg( 'current_section' ) );
			if( count( $matches ) > 1 || ( ( count( $matches ) == 1 ) && ( $matches[ 0 ] != $entry->get( 'id' ) ) ) ) {
				throw new SPException( SPLang::e( 'FIELD_NOT_UNIQUE', $this->name ) );
			}
		}
		/* check if it was editLimit */
		if( $this->editLimit == 0 && !( Sobi::Can( 'entry.adm_fields.edit' ) ) && $dexs ) {
			throw new SPException( SPLang::e( 'FIELD_NOT_AUTH_EXP', $this->name ) );
		}
		/* check if it was editable */
		if( !( $this->editable ) && !( Sobi::Can( 'entry.adm_fields.edit' ) ) && $dexs && $entry->get( 'version' ) > 1 ) {
			throw new SPException( SPLang::e( 'FIELD_NOT_AUTH_NOT_ED', $this->name ) );
		}
		if( !( $dexs ) ) {
			$data = null;
		}
		return $data;
	}

	/**
	 * Gets the data for a field and save it in the database
	 * @param SPEntry $entry
	 * @return bool
	 */
	public function saveData( &$entry, $request = 'POST' )
	{
		if( !( $this->enabled ) ) {
			return false;
		}

		$data = $this->verify( $entry, $request );
		$time = SPRequest::now();
		$IP = SPRequest::ip( 'REMOTE_ADDR', 0, 'SERVER' );
		$uid = Sobi::My( 'id' );

		/* if we are here, we can save these data */
		/* @var SPdb $db */
		$db =& SPFactory::db();

		/* collect the needed params */
		$params = array();
		$params[ 'publishUp' ] = $entry->get( 'publishUp' );
		$params[ 'publishDown' ] = $entry->get( 'publishDown' );
		$params[ 'fid' ] = $this->fid;
		$params[ 'sid' ] = $entry->get( 'id' );
		$params[ 'section' ] = Sobi::Reg( 'current_section' );
		$params[ 'lang' ] = Sobi::Lang();
		$params[ 'enabled' ] = $entry->get( 'state' );
		$params[ 'baseData' ] = strip_tags( $db->escape( $data ) );
		$params[ 'approved' ] = $entry->get( 'approved' );
		$params[ 'confirmed' ] = $entry->get( 'confirmed' );
		/* if it is the first version, it is new entry */
		if( $entry->get( 'version' ) == 1 ) {
			$params[ 'createdTime' ] = $time;
			$params[ 'createdBy' ] = $uid;
			$params[ 'createdIP' ] = $IP;
		}
		$params[ 'updatedTime' ] = $time;
		$params[ 'updatedBy' ] = $uid;
		$params[ 'updatedIP' ] = $IP;
		$params[ 'copy' ] = !( $entry->get( 'approved' ) );

		/* save it */
		try {
			/* Notices:
			 * If it was new entry - insert
			 * If it was an edit and the field wasn't filled before - insert
			 * If it was an edit and the field was filled before - update
			 *     " ... " and changes are not autopublish it should be insert of the copy .... but
			 * " ... " if a copy already exist it is update again
			 * */
			$db->insertUpdate( 'spdb_field_data', $params );
		}
		catch ( SPException $x ) {
			Sobi::Error( __CLASS__, SPLang::e( 'CANNOT_SAVE_DATA', $x->getMessage() ), SPC::WARNING, 0, __LINE__, __FILE__ );
		}

		/* if it wasn't edited in the default language, we have to try to insert it also for def lang */
		if( Sobi::Lang() != SOBI_DEFLANG ) {
			$params[ 'lang' ] = SOBI_DEFLANG;
			try {
				$db->insert( 'spdb_field_data', $params, true );
			}
			catch ( SPException $x ) {
				Sobi::Error( __CLASS__, SPLang::e( 'CANNOT_SAVE_DATA', $x->getMessage() ), SPC::WARNING, 0, __LINE__, __FILE__ );
			}
		}
	}

	/**
	 * Shows the field in the search form
	 * @param bool $return return or display directly
	 * @return string
	 */
	public function searchForm( $return = false )
	{
		if( $this->searchMethod == 'general' ) {
			return false;
		}

		if( $this->searchMethod == 'range' ) {
			return $this->rangeSearch( $this->searchRangeValues );
		}

		$db =& SPFactory::db();
		$fdata = array();
        try {
        	$db->dselect( array( 'baseData' ), 'spdb_field_data', array( 'fid' => $this->fid, 'copy' => '0', 'enabled' => 1 ), 'field( lang, \''.Sobi::Lang().'\'), baseData', 0, 0, 'baseData' );
        	$data = $db->loadResultArray();
        } catch ( SPException $x ) {
        	Sobi::Error( $this->name(), SPLang::e( 'CANNOT_GET_FIELDS_DATA_DB_ERR', $x->getMessage() ), SPC::WARNING, 0, __LINE__, __FILE__ );
        }

        $data = ( ( array ) $data );
        if( count( $data ) ) {
        	$fdata[ '' ] = Sobi::Txt( 'FD.INBOX_SEARCH_SELECT', array( 'name' => $this->name ) );
        	foreach ( $data as $i => $d ) {
        		$fdata[ strip_tags( $d ) ] = strip_tags( $d );
        	}
        }
        return SPHtml_Input::select( $this->nid, $fdata, $this->_selected, false, array( 'class' => $this->cssClass.' '.Sobi::Cfg( 'search.form_list_def_css', 'SPSearchSelect' ), 'size' => '1' ) );
	}

	/**
	 * @param string $data
	 * @param int $section
	 * @param bool $regex
	 * @return array
	 */
	public function searchString( $data, $section, $regex = false )
	{
		return $this->search( ( $regex ? $data : "%{$data}%" ), $section );
	}

	/**
	 * @param string $data
	 * @param int $section
	 * @param bool $startWith
	 * @return array
	 */
	public function searchSuggest( $data, $section, $startWith = true )
	{
		$terms = array();
		$data = $startWith ? "{$data}%" : "%{$data}%";
        try {
        	$terms = SPFactory::db()
        		->dselect( 'baseData', 'spdb_field_data', array( 'fid' => $this->fid, 'copy' => '0', 'enabled' => 1, 'baseData' => $data, 'section' => $section ) )
        		->loadResultArray();        	
        }
        catch ( SPException $x ) {
        	Sobi::Error( $this->name(), SPLang::e( 'CANNOT_SEARCH_DB_ERR', $x->getMessage() ), SPC::WARNING, 0, __LINE__, __FILE__ );
        }
        return $terms;
	}
	
	/**
	 * @param string $data
	 * @param int $section
	 * @return array
	 */
	private function search( $data, $section )
	{
		$sids = array();
        try {
        	SPFactory::db()->dselect( 'sid', 'spdb_field_data', array( 'fid' => $this->fid, 'copy' => '0', 'enabled' => 1, 'baseData' => $data, 'section' => $section ) );        	
        	$sids = SPFactory::db()->loadResultArray();
        }
        catch ( SPException $x ) {
        	Sobi::Error( $this->name(), SPLang::e( 'CANNOT_SEARCH_DB_ERR', $x->getMessage() ), SPC::WARNING, 0, __LINE__, __FILE__ );
        }
		return $sids;
	}

	/* (non-PHPdoc)
	 * @see Site/opt/fields/SPFieldType#searchData()
	 */
	public function searchData( $request, $section )
	{
		if( is_array( $request ) && ( isset( $request['from'] ) || isset( $request['to'] ) ) ) {
			return $this->searchForRange( $request, $section );
		}
		else {
			return $this->search( "REGEXP:^{$request}$", $section );
		}
	}
}
?>
