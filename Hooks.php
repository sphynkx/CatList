<?php

## work pages:
## https://lahwiki.sphynkx.org.ua/Категория:Женщины
## https://lahwiki.sphynkx.org.ua/Категория:Мужчины
## https://lahwiki.sphynkx.org.ua/Tech:Тест_CatList
## https://lahwiki.sphynkx.org.ua/Tech:Для_теста_CatList
## !!Dont forget to revert them in prev.state

use Wikimedia\Rdbms\IConnectionProvider;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;

class CatListHooks {

    public static function wfCatListSetup( Parser $parser ) {
	$parser->setHook( 'catlist', 'CatListHooks::wfCatList' );
    }



/*
* Prepare namespace text - convert from numeric, set ucfirst()-like for cyrillic. Add colon if not NS_MAIN.
* Also output "dump" of all namespaces
*/
    private static function getNsName($ns, $showList=0){
	$nsList = array_flip(MediaWikiServices::getInstance()->getContentLanguage()->getNamespaceIds());
	## For tag parameter 'shownamespaces'
	if ($showList == 1){
	    return print_r($nsList, true);
	}

	$ns = $nsList[$ns];
	$ns = ($ns !== 0) ? mb_strtoupper(mb_substr($ns, 0, 1)).mb_substr($ns, 1).':' : '';
	return $ns;
    }


/*
* Base function
*/
    public static function wfCatList( $input, array $args, Parser $parser, PPFrame $frame ) {
	## List of all namespaces (tag parameter 'shownamespaces')
	if ( isset($args['shownamespaces']) ) {
	    return self::getNsName('', 1);
	}

	## Get category name from between of tags. Delete 'Category:' prefix
	if( !isset( $input ) or $input === '' ) {
	    $input = preg_replace('/.*?:/', '', $parser->getPage()->prefixedText);
	}
	## Set namespaces from tag parameter (as comma separated numeric values). Default is Main
	$namespaces = isset( $args['namespaces'] ) ? $args['namespaces'] : '0';
	$catPages = self::getCatPages($input, $namespaces); ## gets pages titles from SQL request

## see also extensions/Translate_139/TranslateUtils.php:66
## about to get wikisrc of page
	$itemCount = 0;

## Plug in the GoTop template
	if (isset($args['gotop'])) {
		$outp = $parser->recursiveTagParse( '{{Template:GoTop}}', $frame );
	}else{$outp = '';}

## Not used from here??
#	$toc_letters = [];
#	$toc_current = $toc_next = '';

	foreach ($catPages as $cp) {
	    $pt = preg_replace('/_/', ' ', $cp->page_title);

	    ## Prepare namespace text
	    $ns = self::getNsName($cp->page_namespace);
	    $thumb = self::getThumbItem($pt, $ns);

	    ## Filter only pages with special infoboxes
	    if (isset($args['templates'])) {
		$tpls = preg_split('/,\s*/', $args['templates']);
		## Filter by infoboxes and namespaces
		if ( in_array($thumb['template'], $tpls) and isset($cp->page_namespace) ) {
########### ToFix: repeated code
	if (isset($args['toc'])) {
	    $toc_current = mb_substr($pt, 0, 1);

	    if( $toc_current !== $toc_next){
		$toc_next = $toc_current;
		$outp .='<a name="'.$toc_current.'"><h2>'. $toc_current . '</h2></a>';
	    }
	    $toc_letters[] = '<a href="#'.$toc_current.'">' . $toc_current . '</a>';
	}
########### /ToFix: repeated code

		    $outp .= $parser->recursiveTagParse( $thumb['code'], $frame );
		    $itemCount++;
		}
	    }
	    else{
########### ToFix: repeated code
	if (isset($args['toc'])) {
	    $toc_current = mb_substr($pt, 0, 1);

	    if( $toc_current !== $toc_next){
		$toc_next = $toc_current;
		$outp .='<a name="'.$toc_current.'"><h2>'. $toc_current . '</h2></a>';
	    }
	    $toc_letters[] = '<a href="#'.$toc_current.'">' . $toc_current . '</a>';
	}
########### /ToFix: repeated code

		$outp .= $parser->recursiveTagParse( $thumb['code'], $frame );
		$itemCount++;

	    }
	}## end of foreach

	if (isset($args['toc'])) {
	    $collapsed = ($args['toc']=='collapsed') ? 'mw-collapsed' : '';
	    $toc_letters = array_unique($toc_letters);
	    $toc.='<div class="toccolours mw-collapsible '. $collapsed . '" data-expandtext="+" data-collapsetext="-" style="width:100%; display: table-cell; margin: 0.5em 0 0 2em"><h3>' . wfMessage( 'catlist-toc' )->plain() . '</h3><div class="mw-collapsible-content" style="text-align: center"><h2>'.implode(' ', $toc_letters) . '</h2></div></div><br>';
	    }else{
		$toc = '';
	    }

	## Build the final ouput
	if( isset($args['caption']) ){
	    $caption = '<h2>' . $parser->recursiveTagParse( $args['caption'], $frame ) . '</h2>' .
		$toc .
		$itemCount .
		' ' . wfMessage( 'catlist-items' )->text() . '<br>';
	    $caption = preg_replace('/__cat__/', trim($parser->recursiveTagParse( "[[:Category:".$input."|".$input."]]", $frame )), $caption);
	}
	else{
	    $caption = '<h2>' . wfMessage( 'catlist-title' )->text() . ' '.
	    trim($parser->recursiveTagParse( "[[:Category:".$input."|".$input."]]", $frame )) .
		':</h2>' .
		$toc .
		$itemCount .
		' ' . wfMessage( 'catlist-items' )->text() . '<br>';
	}
	return $caption . $outp;
    }




/*
* Get pages list in certain category. Request to DB.
*/
    public static function getCatPages($catName, $namespace = 0) {
	$catName = preg_replace('/(.*?:)/', '', $catName);

##	$dbr = wfGetDB( DB_REPLICA ); ## Deprecated
	$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
## Another method: https://github.com/wikimedia/mediawiki-extensions-CategoryTests/blob/master/ExtCategoryTests.php:33
	$conds = "cl_to = '" . $catName . "' AND cl_from = page_id AND page.page_namespace IN (" . trim($namespace) . ")";
	$db_outp = $dbr->select(
	    ['categorylinks', 'page'],
	    ['categorylinks.cl_from','categorylinks.cl_to','categorylinks.cl_type','page.page_title','page.page_namespace','page.page_id AS page_id'],
	    $conds,
	    __METHOD__,
	    [
		'ORDER BY' => 'page.page_title ASC',
	    ]
	);
	return $db_outp; ## array of pages
    }




/*
Func gets title of page (string), gets wikitext of page, catch some vars from infobox and generate thumb-box
Returns array of code and infobx name
*/
    public static function getThumbItem($pageTitle, $nameSpace){
	$title = Title::newFromText( $nameSpace.$pageTitle );

	$pageTitle = trim($pageTitle);
	$store = MediaWikiServices::getInstance()->getRevisionStoreFactory()->getRevisionStore( );
	$rev = $store->getRevisionByTitle( $title );
	$wikitext = $rev ? $rev->getContent( SlotRecord::MAIN )->getText() : null;

	$wikitext = preg_replace('/\n/','', $wikitext);
	$wikitext = preg_replace('/<!--.*?-->/iu','', $wikitext);

	preg_match('/{{(.*?)\|/', $wikitext, $tplname);
	$tplname = trim($tplname[1]);

	preg_match('/^.*?\|\s*?Синонимы\s*?=(.*?)\|/u', $wikitext, $syn);
	$syn = (strlen( $syn[1]) > 2 ) ? '<hr>([['. preg_replace('/;\s?/', ']]; [[', trim($syn[1])) . ']])' : '';

	preg_match('/^.*?\|\s*?НатСинонимы\s*?=(.*?)\|/u', $wikitext, $natsyn);
	$natsyn = (strlen( $natsyn[1]) > 2 ) ? '<hr>([['. preg_replace('/;\s?/', ']]; [[', trim($natsyn[1])) . ']])' : '';

	preg_match('/\|\s?Историческая\s+=(.*?)\|/', $wikitext, $hist);
	preg_match('/\|\s*?Дата\sсмерти\s*?=(.*?)\|/u', $wikitext, $ddate);
	$border = ($hist[1] ==1) ? 'gold' : (strlen($ddate[1])>2 ? '#777777' : '#ffffff');

	preg_match('/(Изображение\s+=\s*)(.*?)\|/', $wikitext, $img);
	$img = (strlen( $img[1]) > 2 ) ? '[[File:' . trim($img[2]) : '[[File:Unknown-person.png';
	$img = <<<EOD
<div style="border: 10px solid $border; display: inline-block; width=20px; height=20px; margin: 1%; vertical-align: top;">
$img|x120px|thumb|center|top|link=$nameSpace$pageTitle|<center>'''[[$nameSpace$pageTitle]]'''$syn$natsyn</center>]]
</div>
EOD;

    return ['code' => $img, 'template' => $tplname];
    }

}
