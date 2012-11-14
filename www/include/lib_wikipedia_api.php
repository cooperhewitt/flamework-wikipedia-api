<?php

	loadlib("http");

	$GLOBALS['wikipedia_api_endpoint'] = 'http://en.wikipedia.org/w/api.php';

	# http://www.mediawiki.org/wiki/API:Main_page
	# http://en.wikipedia.org/w/api.php

	# http://www.ibm.com/developerworks/opensource/library/x-phpwikipedia/index.html
	# http://stackoverflow.com/questions/964454/how-to-use-wikipedia-api-if-it-exists

	########################################################################

	function wikipedia_api_opensearch($q){

		$args = array(
			'action' => 'opensearch',
			'search' => $q,
		);

		$rsp = wikipedia_api_call($args);

		if (! $rsp['ok']){
			return $rsp;
		}

		$rsp = $rsp['response'];
		$possible = $rsp[1];

		return okay(array(
			'results' => $possible,
		));
	}

	########################################################################

	# http://www.mediawiki.org/wiki/API:Query

	# Note: this isn't set up deal with multiple queries (e.g. foo|bar|baz)

	function wikipedia_api_query($q, $more=array()){

		$defaults = array(
			'prop' => 'info|extracts|revisions',
			'rvprop' => 'content',
		);

		$more = array_merge($defaults, $more);

		$args = array(
			'action' => 'query',
			'indexpageids' => 1,
			'prop' => $more['prop'],
			'rvprop' => $more['rvprop'],
		);

		$query_by = ($more['by_pageid']) ? 'pageids' : 'titles';
		$args[ $query_by ] = $q;

		$rsp = wikipedia_api_call($args);

		if (! $rsp['ok']){
			return $rsp;
		}

		$rsp = $rsp['response'];

		$pageids = $rsp['query']['pageids'];
		$pageid = $pageids[0];

		if ($pageid == '-1'){
			return not_okay("no results");
		}

		$pages = $rsp['query']['pages'];
		$result = $pages[$pageid];

		if (isset($result['revisions'])){
			$result['raw'] = $result['revisions'][0]['*'];
			unset($result['revisions']);
		}

		if (isset($more['follow_redirects'])){

			# Honestly, it's easier than the regular expresssion for
			# [[ ]] which is sad making... (20120726/straup)

			if (preg_match("/REDIRECT ([^<]+)/i", $result['extract'], $m)){

				$title = trim($m[1]);

				$_more = $more;
				unset($_more['follow_redirects']);

				$rsp = wikipedia_api_query($title, $_more);
				return $rsp;
			}
		}

		return okay($result);
	}

	########################################################################

	function wikipedia_api_call($args=array()){

		$args['format'] = 'json';

		$url = $GLOBALS['wikipedia_api_endpoint'] . '?' . http_build_query($args);

		$hash = md5($url);

		# sudo make me a bucket of strings and rotate them...

		$headers = array(
			'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; rv:12.0) Gecko/20120403211507 Firefox/14.0.1',
		);

		$more = array(
			'http_timeout' => 10
		);

		$rsp = http_get($url, $headers, $more);

		if (! $rsp['ok']){
			return $rsp;
		}

		$data = json_decode($rsp['body'], 'as hash');

		if (! $data){
			return not_okay("failed to parse JSON");
		}

		return okay(array(
			'response' => $data
		));
	}

	########################################################################
?>
