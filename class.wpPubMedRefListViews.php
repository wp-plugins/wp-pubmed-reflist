<?php

class wpPubMedReflistViews{

	function __construct(){

	}
	

	public static function query_form_text() {
		echo '<p>Edit Queries. Queries can be any valid pubmed query, '.
		'or you can use query keys as *key* and query text using || (two pipes) as an OR separator</p>'.
		'<p>Extras is for citations of publications not listed in pubmed. Put formatted citations in this field, one citation per line</p>';
	}

	public static function faclist_text() {
		echo '<p>Add new search key.  Search keys are used to tell the shortcode what query '.
		'to use and can be almost any arbitrary unquoted text. We use names of faculty members, '.
		'or mnemonics for searches like "recent"</p>';
	}
	
	public static function styles_form_text(){
		echo '<p>Add new styles by name.</p>';
	}

	public static function styles_data_form_text(){
		echo '<p>Edit the formats used to display references</p>';
	}
	
	public static function styles_ital_form_text(){
		echo "List of items that should be italicized in article titles (e.g. species names). Enter one item per line";
	}

	/*
	Takes array of query results and formats based on a style
	*/
	public function format_refs($refs, $style, $wrap, $limit){
		$html = '';
		$reflist = array();
		$formats = get_option('wp_pubmed_reflist_styles');
		if(!isset($formats['styleprops'][$style]['format'])){
			$style = $formats['default_style'];
		}	
		$template = $formats['styleprops'][$style]['format'];
		foreach ($refs['pmid'] as $ref){
			$reference = $template;
			# authorlist
			$reference = str_replace('_Author', $this->authorlist($ref, $formats['styleprops'][$style]), $reference);
			# Epub date
			$reference = str_replace('_Epub', $ref['EPub'], $reference);
			# title
			$ref['Title'] = self::italicize($ref['Title'], $formats);
			# do _TitleL first or _Title will hit it.
			# Links
			$fields = array('_TitleL','_DOI','_PMIDL','_PMID','_PMCL', '_PMC');
			foreach ($fields as $field){
				$reference = str_replace($field, $this->links($ref, $field), $reference);
			}
			# other fields
			$fields = array('_Year','_Title','_Journal','_Volume', '_Issue', '_Pages' );
			foreach ($fields as $field){
				$reference = str_replace( $field, $ref[trim($field,'_')], $reference);
			}
			# clean up some formatting issues
			$reference = str_replace(array('..','. .','()'), array('.','.',''), $reference);
			$reflist[] = $reference;
		}
		foreach($refs['extras'] as $extra){
			if($extra != '') $reflist[] = $extra;
		}
		#echo "<pre>".print_r($reflist, true)."</pre>";
		if ($limit < 0){	
			$limit = abs($limit) - 1;
			if(count($reflist) < $limit) $limit = count($reflist);
			$k = rand(0, $limit-1);
			$reflist = array($reflist[$k]); 
		}		
		# wrap the references in $wrap
		switch ($wrap){
			case 'p':
				$html = "<p>".implode("</p><p>", $reflist)."</p>";
				break;
			case 'ol':
			case 'ul':
			default:
				$html = "<$wrap><li>".implode("</li><li>", $reflist)."</$wrap>";
		}
		return $html;
	}
	
	function authorlist($ref, $styleprops){
		if(	!isset($styleprops['authlimit']) || 
			$styleprops['authlimit'] == '' ||
			count($ref['AuthorList']) < $styleprops['authlimit']
			){
			return $ref['Authors'];
		}
		# else we have too many authors
		$alimit = $styleprops['authlimit'];
		$ashow = $alimit;
		# overwrite $ashow if it's set
		if(isset($styleprops['authshow']) && $styleprops['authshow'] != ''){
			$ashow = $styleprops['authshow'];
		}
		$authorlist = array_slice($ref['AuthorList'], 0, $ashow);
		return implode(', ', $authorlist)." <i>et al.</i>";
	}
	
	function links($ref, $field){
		$text = '';
		$prefix = '';
		switch($field){
			case '_DOI':
				if(isset($ref['xrefs']['doi'])){
					$doi = $ref['xrefs']['doi'];			
					$text = "doi: <a href='http://dx.doi.org/$doi'>$doi</a>";
				}
				break;
			case '_PMIDL':
				$prefix = 'PubMed ';
			case '_PMID':
				if(isset($ref['PMID'])){
					# should never evaluate false, since the refs are from pubmed, but just in case
					$pmid = $ref['PMID'];			
					$text = "$prefix<a href='http://www.ncbi.nlm.nih.gov/pubmed/$pmid'>PMID:$pmid</a>";
				}
				break;	
			case '_PMCL':
				$prefix = 'PubMed Central ';
			case '_PMC':
				if(isset($ref['xrefs']['pmc'])){
					$pmcid = $ref['xrefs']['pmc'];			
					$text = "$prefix<a href='http://www.ncbi.nlm.nih.gov/pmc/articles/$pmcid'>$pmcid</a>";
				}
				break;	
			case '_TitleL':	
				if(isset($ref['PMID'])){
					# should never evaluate false, since the refs are from pubmed, but just in case
					$pmid = $ref['PMID'];			
					$text = "<a href='http://www.ncbi.nlm.nih.gov/pubmed/$pmid'>".$ref['Title']."</a>";
				}else{
					$text = $ref['Title'];
				}
			
		}
		return $text;
	}
	/*
	italicize species names
	*/
	static function italicize($text, $formats){
	#	echo "<br>".__METHOD__."<br><pre>".print_r($formats, true)."</pre><br>";
		$ital_list = explode("\n", $formats['itals']);
		foreach ($ital_list as $ital_item){
			$ital_item = trim($ital_item);
			if($ital_item == '') continue;
			$text = preg_replace("/\b($ital_item)\b/", '<i>$0</i>', $text );
		}
		return $text;
	}
	
	/*
	Display the help tab
	'limit'         => '',
	'style'         => '',
	'wrap'         => 'ol',
	'linktext'	=> 'Search PubMed',
	'showlink' => ''

	*/
	public static function help(){
		echo "
	<h2>Using the pmid-refs shortcode</h2>
	<p>The [pmid-refs] shortcode has one required parameter and several optional ones</p>
	<ul>
	<li><b>key</b> [<i>required</i>] the key for the query to run</li>
	<li><b>limit</b> if positive, this determines the number of references to show. If negative, one reference will be randomly selected every day from a pool set by limit </li>
	<li><b>style</b> Use this to change the formatting of the references</li>
	<li><b>wrap</b> Sets how to display the list. The default is to make an ordered list (numbered). Other allowed values are 'p' for paragraphs and 'ul' for an unordered list (bullets)</li>
	<li><b>linktext</b> What to show on the link that runs the query on Pubmed. Default is 'Search PubMed'</li>
	<li><b>showlink</b> determines whether a link to pubmed to run the query will be added below the reference list. Allowed values are 'true' (default), 'false', and 'link only'
	</li>
	</ul>
	<h3>Examples</h3>
	Examples assume you have a query with the key name 'smith'
	<table border =1>
	<tr><th>Tag</th><th>Description</th></tr>
	<tr><td>[pmid-refs key=smith]</td><td>Display the default number of references for the query for key=smith. The default is 20</td></tr>
	<tr><td>[pmid-refs key=smith limit=10]</td><td>Use limit to change the number of references for the query for key=smith to 10</td></tr>
	<tr><td>[pmid-refs key=smith limit=10]</td><td>Use limit to change the number of references for the query for key=smith to 10</td></tr>
	<tr><td>[pmid-refs key=smith limit=10]</td><td>Use limit to change the number of references for the query for key=smith to 10</td></tr>
	<tr><td>[pmid-refs key=smith limit=10 style=PNAS wrap=p]</td><td>Change the formatting to PNAS style and make each reference a paragraph. </td></tr>
	<tr><td>[pmid-refs key=smith showlink='link only']</td><td>Omits the references and just links to PubMed.</td></tr>
	<tr><td>[pmid-refs key=smith_jbc showlink='false'][pmid-refs key=smith showlink='link only']</td><td>By combining two shortcodes where the showlink is different, you can display the results of one query (e.g. just Smith's papers in JBC) while linking to a different PubMed search (e.g. all of Smith's papers)</td></tr>
	</table>

	
	<h2>Managing queries</h2>
	<p>Query management is done under the Queries tab. There are two sections to that form. One allows you to add new query keys, the other sets information associated with that key, or delete an entry</p>
	<h3>Keys</h3>
	<p>keys are whatever you want to use to invoke a particular query in the shortcode. Keys should only use alphanumeric characters, plus spaces, parentheses and underscores. Other characters will be rejected.</p>
	<h3>Queries</h3>
	<p>Queries are composed  text that works as a Pubmed search string. See <a href='http://www.ncbi.nlm.nih.gov/books/NBK3827/'>PubMed Help</a> for documentation on Pubmed query syntax. The queries you use can be quite complex and we often have to make them complicated when authors have common names that bring up false positives. Here is an example from the <a href='http://biochemistry.tamu.edu'>Biochemistry website at TAMU</a></p>
	<pre>zhang j[au] AND (chiu w[au] OR levitt m[au] OR (college station[ad] AND biochemistry[ad]))+NOT+25300236[pmid]+NOT+cassava+ NOT+20375156[pmid]</pre>
	<p>Here we are catching any publications where Dr. Zhang has bichemistry and college station in the address field OR if the reference has his graduate or postdoctoral mentor as a coauthor. We reject some specific keywords and PMIDs based on false positives, including some where there is another J. Zhang on our campus who was an author in a multi-institution genomics paper with lots of authors. </p>
	<h4>Nested queries</h4>
	<p>We can also use keys as variables in other queries for example, if we have keys for kaplan, kapler, and kunkel, we can combine their query results and add another clause that filters the joint results to only those that were published in a particular journal</p>
	<pre>(|| kaplan || kapler || kunkel ||)AND
(pnas[ta] OR biochemistry[ta] OR j biol chem[ta])</pre>
<p>The double pipes ('||') separate the keys by a logical OR. Queries can be nested to multiple levels to make composition of complicated queries easier.</p>
	<h3>Extras</h3>
	<p>Sometimes you want to include a reference that is not indexed in PubMed. Add these, one per line, to the extras field in the format you want to use for display. Note that we use this sparingly, as the extras will ignore the style specifications. What you enter is what you get.</p>
	<h2>Managing output styles</h2>
	<p>Starting with version 0.7, WP PubMed Reflist allows you to create styles for how references will be displayed. The plugin comes with several formats preinstalled. The default style can be selected or styles can be specified in the shorttag parameters</p>	
	<h3>Formatting codes</h3>
	The styles are templates where special codes are replaced by specified content. Each of the formatting codes starts with a _ so it won't interfere with normal text in your template. The following codes are available (most have pretty obvious meanings).
<table border ='1'>
<tr><th>Code</th><th>Replacement</th><tr>
<tr><td>_Author</td><td>Author list</td><tr>
<tr><td>_Year</td><td>Year</td><tr>
<tr><td>_Title</td><td>Title</td><tr>
<tr><td>_TitleL</td><td>Title with link to Pubmed</td><tr>
<tr><td>_Volume</td><td>Volume</td><tr>
<tr><td>_Issue</td><td>Issue</td><tr>
<tr><td>_Pages</td><td>Pages</td><tr>
<tr><td>_Epub</td><td>Date of electronic publication</td><tr>
<tr><td>_DOI</td><td>DOI with label and link</td><tr>
<tr><td>_PMID</td><td>unlabeled PMID with link to PubMed</td><tr>
<tr><td>_PMIDL</td><td>PMID link with PubMed prefix label. </td><tr>
<tr><td>_PMC</td><td>unlabeled Pubmed Central link</td><tr>
<tr><td>_PMCL</td><td>labeled Pubmed Central link</td><tr>
</table>	
	
	<h3>Author lists</h3>
	<p>Styles can set how many authors to display before using <i>et al.</i>. There are two numbers that control this: a <b>limit</b> and a <b>show</b> number. If the display number is not set, the maximum number of authors shown will be the same as the limit. </p>
	<h3>Italicizing keywords</h3>
	<p>Starting with version 0.7 WP Pubmed Reflist keeps a list of keywords/phrases that will be automatically italicized in titles. This can be used for things like species names or other latin phrases. Some examples are preloaded, but you can edit the list.</p>
	<h2>Bug reports and suggestions</h2>
	<p>I think I've figured out how to get wordpress.org to email me when <a href='https://wordpress.org/support/plugin/wp-pubmed-reflist'>support posts are made</a>. </p>
	<h2>Donate</h2>
	If this plugin is useful to you please send a donation to the Biochemistry/Biophysics improvement fund for the Dept. of Biochemistry and Biophysics at Texas A&M. Donations can be made through the <a href='http://txamfoundation.com/s/1436/gid3give/2014/start.aspx?gid=3&pgid=61'>Texas A&M Foundation</a>. It won't give me more time to work on the plugin, but it's tax-deductible and it will go to some other worthy activity.
	";
	}

}