<?php

## work pages:
## https://lahwiki.sphynkx.org.ua/Категория:Женщины
## https://lahwiki.sphynkx.org.ua/Tech:Тест_CatList
## dont forget to revert them in prev.state

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

    if ($showList == 1){
	return print_r($nsList, true);
    }

    $ns = $nsList[$ns];
    $ns = ($ns !== 0) ? mb_strtoupper(mb_substr($ns, 0, 1)).mb_substr($ns, 1).':' : '';
    return $ns;
}



    public static function wfCatList( $input, array $args, Parser $parser, PPFrame $frame ) {
## List of all namespaces
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
	$outp = '';
	foreach ($catPages as $cp) {
	    $pt = preg_replace('/_/', ' ', $cp->page_title);

## Prepare namespace text
	    $ns = self::getNsName($cp->page_namespace);

	    $thumb = self::getThumbItem($pt, $ns);

#preg_match('/.*(Изображение)\=.*/m', $thumb['template'], $tplname);
##preg_match('/(empty)/', $thumb['template'], $tplname);
#$tpl = $thumb['template'];//trim($tplname[0]);



	    if (isset($args['templates'])) {
		$tpls = preg_split('/,\s*/', $args['templates']);
## The 'and..' need because pages w/o infoboxes will pass by self::getThumbItem($pt)['template']
## doesnt filter by tpl		if ( in_array(self::getThumbItem($pt, $ns)['template'], $tpls, true) or isset($cp->page_namespace) ) {
		if ( in_array($thumb['template'], $tpls) and isset($cp->page_namespace) ) {
		    $outp .= $parser->recursiveTagParse( $thumb['code'], $frame );
		    $itemCount++;
		}
	    }
	    else{
		$outp .= $parser->recursiveTagParse( $thumb['code'], $frame );
		$itemCount++;
	    }
	}

## Build the final ouput
	if( isset($args['caption']) ){
	    $caption = '<h2>' . $args['caption'] . '</h2>' .
		$itemCount .
		' ' . wfMessage( 'catlist-items' )->text() . '<br>';
	    $caption = preg_replace('/\{\{cat\}\}/', trim($parser->recursiveTagParse( "[[:Category:".$input."|".$input."]]", $frame )), $caption);
	}
	else{
	    $caption = '<h2>' . wfMessage( 'catlist-title' )->text() . ' '.
	    trim($parser->recursiveTagParse( "[[:Category:".$input."|".$input."]]", $frame )) .
		':</h2>' .
		$itemCount .
		' ' . wfMessage( 'catlist-items' )->text() . '<br>';
	}
	return $caption . $outp;
    }




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
## Set namespace as ucfirst for cyrillic. Add colon if not NS_MAIN
###	$nameSpace = ($nameSpace !== '') ? mb_strtoupper(mb_substr($nameSpace, 0, 1)).mb_substr($nameSpace, 1).':' : '';

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