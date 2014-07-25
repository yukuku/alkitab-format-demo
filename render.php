<style>
.box {
	width: 200px;
	background: #cee;
}
.startpara {
	margin-top: 1em;
	text-indent: 2.5em;
}
.leading0 {
	margin-left: 0;
}
.leading1 {
	margin-left: 1em;
}
.leading2 {
	margin-left: 2em;
}
.leading3 {
	margin-left: 3em;
}
.leading4 {
	margin-left: 4em;
}
.red {
	color: #f00;
}
.italic {
	font-style: italic;
}

</style>
<?php
	$text = $_REQUEST['text'];

	error_reporting(E_ALL);

	// buang @@ kalo ada di depan
	if (substr($text, 0, 2) == '@@') {
		$text = substr($text, 2);
	}


	$spans = [];

	function addSpan($name, $from, $to) {
		global $spans;
		$spans[] = ['name' => $name, 'from' => $from, 'to' => $to];
	}

	/**
	 * @param paraType if -1, will apply the same thing as when paraType is 0 and firstLineWithVerseNumber is true.
	 * @param firstLineWithVerseNumber If this is formatting for the first paragraph of a verse and that paragraph contains a verse number, so we can apply more lefty first-line indent.
	 * This only applies if the paraType is 0.
	 * @param dontPutSpacingBefore if this paragraph is just after pericope title or on the 0th position, in this case we don't apply paragraph spacing before.
	 */
	function applyParaStyle(&$sb, $paraType, $startPara) {
		$len = strlen($sb);
		
		if ($startPara == $len) return;
		
		switch ($paraType) {
		case -1:
			addSpan('leading0', $startPara, $len);
			break;
		case '0':
			addSpan('leading0', $startPara, $len);
			break;
		case '1':
			addSpan('leading1', $startPara, $len);
			break;
		case '2':
			addSpan('leading2', $startPara, $len);
			break;
		case '3':
			addSpan('leading3', $startPara, $len);
			break;
		case '4':
			addSpan('leading4', $startPara, $len);
			break;
		case '^':
			addSpan('startpara', $startPara, $len);
			break;
		}
	}

	// @@ = start a verse containing paragraphs or formatting
	// @0 = start with indent 0 [paragraph]
	// @1 = start with indent 1 [paragraph]
	// @2 = start with indent 2 [paragraph]
	// @3 = start with indent 3 [paragraph]
	// @4 = start with indent 4 [paragraph]
	// @6 = start of red text [formatting]
	// @5 = end of red text   [formatting]
	// @9 = start of italic [formatting]
	// @7 = end of italic   [formatting]
	// @8 = put a blank line to the next verse [formatting]
	// @^ = start-of-paragraph marker
	// @< to @> = special tags (not visible for unsupported tags) [can be considered formatting]
	// @/ = end of special tags (closing tag) (As of 2013-10-04, all special tags must be closed) [can be considered formatting]


	$text_len = strlen($text);
	
	/**
	 * '0'..'4', '^' indent 0..4 or new para
	 * -1 undefined
	 */
	$paraType = -1; 
	/**
	 * position of start of paragraph
	 */
	$startPara = 0;
	/**
	 * position of start red marker
	 */
	$startRed = -1;
	/**
	 * position of start italic marker
	 */
	$startItalic = -1;
	/**
	 * whether we are inside a tag (between @< and @>)
	 */
	$inSpecialTag = false;

	// final result
	$sb = '';

	$pos = 0; // we start after "@@"

	while (true) {
		if ($pos >= $text_len) {
			break;
		}

		$nextAt = strpos($text, '@', $pos);

		if ($nextAt === false) { // no more, just append till the end of everything and exit
			$sb .= substr($text, $pos, $text_len - $pos);
			break;
		}

		if ($inSpecialTag) { // are we in a tag?
			// we have encountered the end of a tag
			$tag = '';
			$tag .= substr($text, $pos, $nextAt - $pos);
			$pos = $nextAt;
		} else {
			// insert all text until the nextAt
			if ($nextAt != $pos) /* extra check for optimization (prevent call to sb.append()) */ {
				$sb .= substr($text, $pos, $nextAt - $pos);
				$pos = $nextAt;
			}
		}
		
		$pos++;
		// just in case 
		if ($pos >= $text_len) {
			break;
		}

		$marker = $text[$pos];
		switch ($marker) {
			case '0':
			case '1':
			case '2':
			case '3':
			case '4':
			case '^':
				// apply previous
				applyParaStyle($sb, $paraType, $startPara);
				// removed in php version: if (strlen($sb) > 0) {
				// removed in php version: 	$sb .= "\n";
				// removed in php version: }
				// store current
				$paraType = $marker;
				$startPara = strlen($sb);
				break;
			case '6':
				$startRed = strlen($sb);
				break;
			case '5':
				if ($startRed != -1) {
					addSpan('red', $startRed, strlen($sb));
					$startRed = -1;
				}
				break;
			case '9':
				$startItalic = strlen($sb);
				break;
			case '7':
				if ($startItalic != -1) {
					addSpan('italic', $startItalic, strlen($sb));
					$startItalic = -1;
				}
				break;
			case '8':
				$sb .= "\n";
				break;
			case '<':
				$inSpecialTag = true;
				break;
			case '>':
				$inSpecialTag = false;
				break;
			case '/':
				processSpecialTag(sb, tag, inlineLinkSpanFactory, ari);
				break;
		}

		$pos++;
	}
	
	// apply unapplied
	applyParaStyle($sb, $paraType, $startPara);

	function transformSpans() {
		global $spans;
		$sorted = [];

		foreach ($spans as $span) {
			$sorted[] = [
				'name' => $span['name'],
				'type' => 'from',
				'pos' => $span['from'],
			];
			$sorted[] = [
				'name' => $span['name'],
				'type' => 'to',
				'pos' => $span['to'],
			];
		}
		usort($sorted, function($a, $b) {
			if ($b['pos'] == $a['pos']) {
				if ($b['type'] == 'to') return -1;
				return 1;
			}
			return $b['pos'] - $a['pos'];
		});

		return $sorted;
	}

	$sorted = transformSpans();

	$rendered = $sb;
	$fromMarks = [
		'startpara' => "<p class='startpara'>",
		'leading0' => "<p class='leading0'>",
		'leading1' => "<p class='leading1'>",
		'leading2' => "<p class='leading2'>",
		'leading3' => "<p class='leading3'>",
		'leading4' => "<p class='leading4'>",
		'red' => "<span class='red'>",
		'italic' => "<span class='italic'>",
	];
	$toMarks = [
		'startpara' => "</p>",
		'leading0' => "</p>",
		'leading1' => "</p>",
		'leading2' => "</p>",
		'leading3' => "</p>",
		'leading4' => "</p>",
		'red' => "</span>",
		'italic' => "</span>",
	];

	foreach ($sorted as $s) {
		if ($s['type'] == 'from') {
			$mark = $fromMarks[$s['name']];
		}
		if ($s['type'] == 'to') {
			$mark = $toMarks[$s['name']];
		}
		$rendered = substr($rendered, 0, $s['pos']) . $mark . substr($rendered, $s['pos']);
	}
	$rendered = nl2br($rendered);

	echo '<p>rendered: <div class=box>' . $rendered . '</div>';
	echo '<p>sb: <pre>' . $sb . '</pre>';
	echo '<p>spans: <pre>' . var_export($spans, true) . '</pre>';
	echo '<p>sorted spans: <pre>' . var_export($sorted, true) . '</pre>';

	echo '<hr><hr><hr><hr><hr>source code: <plaintext>';
	echo file_get_contents(__FILE__);

?>