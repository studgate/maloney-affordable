<?php
// phpcs:ignoreFile
namespace MonsterInsightsHeadlineToolPlugin;

// setup defines
define( 'MONSTERINSIGHTS_HEADLINE_TOOL_DIR_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Headline Tool
 *
 * @since      0.1
 * @author     Debjit Saha
 */
class MonsterInsightsHeadlineToolPlugin {

	/**
	 * Class Variables.
	 */
	private $emotion_power_words = array();
	private $power_words = array();
	private $common_words = array();
	private $uncommon_words = array();

	/**
	 * Constructor
	 *
	 * @return   none
	 */
	function __construct() {
		$this->init();
	}

	/**
	 * Add the necessary hooks and filters
	 */
	function init() {
		add_action( 'wp_ajax_monsterinsights_gutenberg_headline_analyzer_get_results', array( $this, 'get_result' ) );
	}

	/**
	 * Ajax request endpoint for the uptime check
	 */
	function get_result() {

		// csrf check
		if ( check_ajax_referer( 'monsterinsights_gutenberg_headline_nonce', false, false ) === false ) {
			$content = self::output_template( 'results-error.php' );
			wp_send_json_error(
				array(
					'html' => $content
				)
			);
		}

		// get whether or not the website is up
		$result = $this->get_headline_scores();

		if ( ! empty( $result->err ) ) {
			$content = self::output_template( 'results-error.php', $result );
			wp_send_json_error(
				array( 'html' => $content, 'analysed' => false )
			);
		} else {
			if(!isset($_REQUEST['q'])){
				wp_send_json_error(
					array( 'html' => '', 'analysed' => false )
				);
			}
			$q = (isset($_REQUEST['q'])) ? sanitize_text_field($_REQUEST['q']) : '';
			// send the response
			wp_send_json_success(
				array(
					'result'   => $result,
					'analysed' => ! $result->err,
					'sentence' => ucwords( wp_unslash( $q ) ),
					'score'    => ( isset( $result->score ) && ! empty( $result->score ) ) ? $result->score : 0
				)
			);

		}
	}

	/**
	 * function to match words from sentence
	 * @return Object
	 */
	function match_words( $sentence, $sentence_split, $words ) {
		$ret = array();
		foreach ( $words as $wrd ) {
			// check if $wrd is a phrase
			if ( strpos( $wrd, ' ' ) !== false ) {
				$word_position = strpos( $sentence, $wrd );

				// Word not found in the sentence.
				if ( $word_position === false ) {
					continue;
				}

				// Check this is the end of the sentence.
				$is_end = strlen( $sentence ) === $word_position + 1;

				// Check the next character is a space.
				$is_space = " " === substr( $sentence, $word_position + strlen( $wrd ), 1 );

				// If it is a phrase then the next character must end of sentence or a space.
				if ( $is_end || $is_space ) {
					$ret[] = $wrd;
				}
			} // if $wrd is a single word
			else {
				if ( in_array( $wrd, $sentence_split ) ) {
					$ret[] = $wrd;
				}
			}
		}

		return $ret;
	}

	/**
	 * main function to calculate headline scores
	 * @return Object
	 */
	function get_headline_scores() {
		$input = (isset($_REQUEST['q'])) ? sanitize_text_field($_REQUEST['q']) : '';

		// init the result array
		$result                   = new \stdClass();
		$result->input_array_orig = explode( ' ', wp_unslash( $input ) );

		// strip useless characters
		$input = preg_replace( '/[^A-Za-z0-9 ]/', '', $input );

		// strip whitespace
		$input = preg_replace( '!\s+!', ' ', $input );

		// lower case
		$input = strtolower( $input );

		$result->input = $input;

		// bad input
		if ( ! $input || $input == ' ' || trim( $input ) == '' ) {
			$result->err = true;
			$result->msg = __( 'Bad Input', 'google-analytics-premium' );

			return $result;
		}

		// overall score;
		$scoret = 0;

		// headline array
		$input_array = explode( ' ', $input );

		$result->input_array = $input_array;

		// all okay, start analysis
		$result->err = false;

		// Length - 55 chars. optimal
		$result->length = strlen( str_replace( ' ', '', $input ) );
		$scoret         = $scoret + 3;

		if ( $result->length <= 19 ) {
			$scoret += 5;
		} elseif ( $result->length >= 20 && $result->length <= 34 ) {
			$scoret += 8;
		} elseif ( $result->length >= 35 && $result->length <= 66 ) {
			$scoret += 11;
		} elseif ( $result->length >= 67 && $result->length <= 79 ) {
			$scoret += 8;
		} elseif ( $result->length >= 80 ) {
			$scoret += 5;
		}

		// Count - typically 6-7 words
		$result->word_count = count( $input_array );
		$scoret             = $scoret + 3;

		if ( $result->word_count == 0 ) {
			$scoret = 0;
		} else if ( $result->word_count >= 2 && $result->word_count <= 4 ) {
			$scoret += 5;
		} elseif ( $result->word_count >= 5 && $result->word_count <= 9 ) {
			$scoret += 11;
		} elseif ( $result->word_count >= 10 && $result->word_count <= 11 ) {
			$scoret += 8;
		} elseif ( $result->word_count >= 12 ) {
			$scoret += 5;
		}

		// Calculate word match counts
		$result->power_words        = $this->match_words( $result->input, $result->input_array, $this->power_words() );
		$result->power_words_per    = count( $result->power_words ) / $result->word_count;
		$result->emotion_words      = $this->match_words( $result->input, $result->input_array, $this->emotion_power_words() );
		$result->emotion_words_per  = count( $result->emotion_words ) / $result->word_count;
		$result->common_words       = $this->match_words( $result->input, $result->input_array, $this->common_words() );
		$result->common_words_per   = count( $result->common_words ) / $result->word_count;
		$result->uncommon_words     = $this->match_words( $result->input, $result->input_array, $this->uncommon_words() );
		$result->uncommon_words_per = count( $result->uncommon_words ) / $result->word_count;
		$result->word_balance       = __( 'Can Be Improved', 'google-analytics-premium' );
		$result->word_balance_use   = array();

		if ( $result->emotion_words_per < 0.1 ) {
			$result->word_balance_use[] = __( 'emotion', 'google-analytics-premium' );
		} else {
			$scoret = $scoret + 15;
		}

		if ( $result->common_words_per < 0.2 ) {
			$result->word_balance_use[] = __( 'common', 'google-analytics-premium' );
		} else {
			$scoret = $scoret + 11;
		}

		if ( $result->uncommon_words_per < 0.1 ) {
			$result->word_balance_use[] = __( 'uncommon', 'google-analytics-premium' );
		} else {
			$scoret = $scoret + 15;
		}

		if ( count( $result->power_words ) < 1 ) {
			$result->word_balance_use[] = __( 'power', 'google-analytics-premium' );
		} else {
			$scoret = $scoret + 19;
		}

		if (
			$result->emotion_words_per >= 0.1 &&
			$result->common_words_per >= 0.2 &&
			$result->uncommon_words_per >= 0.1 &&
			count( $result->power_words ) >= 1 ) {
			$result->word_balance = __( 'Perfect', 'google-analytics-premium' );
			$scoret               = $scoret + 3;
		}

		// Sentiment analysis also look - https://github.com/yooper/php-text-analysis

		// Emotion of the headline - sentiment analysis
		// Credits - https://github.com/JWHennessey/phpInsight/
		require_once MONSTERINSIGHTS_HEADLINE_TOOL_DIR_PATH . '/phpinsight/autoload.php';
		$sentiment         = new \PHPInsight\Sentiment();
		$class_senti       = $sentiment->categorise( $input );
		$result->sentiment = $class_senti;

		$scoret = $scoret + ( $result->sentiment === 'pos' ? 10 : ( $result->sentiment === 'neg' ? 10 : 7 ) );

		// Headline types
		$headline_types = array();

		// HDL type: how to, how-to, howto
		if ( strpos( $input, __( 'how to', 'google-analytics-premium' ) ) !== false || strpos( $input, __( 'howto', 'google-analytics-premium' ) ) !== false ) {
			$headline_types[] = __( 'How-To', 'google-analytics-premium' );
			$scoret           = $scoret + 7;
		}

		// HDL type: numbers - numeric and alpha
		$num_quantifiers = array(
			__( 'one', 'google-analytics-premium' ),
			__( 'two', 'google-analytics-premium' ),
			__( 'three', 'google-analytics-premium' ),
			__( 'four', 'google-analytics-premium' ),
			__( 'five', 'google-analytics-premium' ),
			__( 'six', 'google-analytics-premium' ),
			__( 'seven', 'google-analytics-premium' ),
			__( 'eight', 'google-analytics-premium' ),
			__( 'nine', 'google-analytics-premium' ),
			__( 'eleven', 'google-analytics-premium' ),
			__( 'twelve', 'google-analytics-premium' ),
			__( 'thirt', 'google-analytics-premium' ),
			__( 'fift', 'google-analytics-premium' ),
			__( 'hundred', 'google-analytics-premium' ),
			__( 'thousand', 'google-analytics-premium' ),
		);

		$list_words = array_intersect( $input_array, $num_quantifiers );
		if ( preg_match( '~[0-9]+~', $input ) || ! empty ( $list_words ) ) {
			$headline_types[] = __( 'List', 'google-analytics-premium' );
			$scoret           = $scoret + 7;
		}

		// HDL type: Question
		$qn_quantifiers     = array(
			__( 'where', 'google-analytics-premium' ),
			__( 'when', 'google-analytics-premium' ),
			__( 'how', 'google-analytics-premium' ),
			__( 'what', 'google-analytics-premium' ),
			__( 'have', 'google-analytics-premium' ),
			__( 'has', 'google-analytics-premium' ),
			__( 'does', 'google-analytics-premium' ),
			__( 'do', 'google-analytics-premium' ),
			__( 'can', 'google-analytics-premium' ),
			__( 'are', 'google-analytics-premium' ),
			__( 'will', 'google-analytics-premium' ),
		);
		$qn_quantifiers_sub = array(
			__( 'you', 'google-analytics-premium' ),
			__( 'they', 'google-analytics-premium' ),
			__( 'he', 'google-analytics-premium' ),
			__( 'she', 'google-analytics-premium' ),
			__( 'your', 'google-analytics-premium' ),
			__( 'it', 'google-analytics-premium' ),
			__( 'they', 'google-analytics-premium' ),
			__( 'my', 'google-analytics-premium' ),
			__( 'have', 'google-analytics-premium' ),
			__( 'has', 'google-analytics-premium' ),
			__( 'does', 'google-analytics-premium' ),
			__( 'do', 'google-analytics-premium' ),
			__( 'can', 'google-analytics-premium' ),
			__( 'are', 'google-analytics-premium' ),
			__( 'will', 'google-analytics-premium' ),
		);
		if ( in_array( $input_array[0], $qn_quantifiers ) ) {
			if ( in_array( $input_array[1], $qn_quantifiers_sub ) ) {
				$headline_types[] = __( 'Question', 'google-analytics-premium' );
				$scoret           = $scoret + 7;
			}
		}

		// General headline type
		if ( empty( $headline_types ) ) {
			$headline_types[] = __( 'General', 'google-analytics-premium' );
			$scoret           = $scoret + 5;
		}

		// put to result
		$result->headline_types = $headline_types;

		// Resources for more reading:
		// https://kopywritingkourse.com/copywriting-headlines-that-sell/
		// How To _______ That Will Help You ______
		// https://coschedule.com/blog/how-to-write-the-best-headlines-that-will-increase-traffic/

		$result->score = $scoret >= 93 ? 93 : $scoret;

		return $result;
	}

	/**
	 * Output template contents
	 *
	 * @param $template String template file name
	 *
	 * @return String template content
	 */
	static function output_template( $template, $result = '', $theme = '' ) {
		ob_start();
		require MONSTERINSIGHTS_HEADLINE_TOOL_DIR_PATH . '' . $template;
		$tmp = ob_get_contents();
		ob_end_clean();

		return $tmp;
	}

	/**
	 * Get User IP
	 *
	 * Returns the IP address of the current visitor
	 * @see https://github.com/easydigitaldownloads/easy-digital-downloads/blob/904db487f6c07a3a46903202d31d4e8ea2b30808/includes/misc-functions.php#L163
	 * @return string $ip User's IP address
	 */
	static function get_ip() {

		$ip = '127.0.0.1';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			//check ip from share internet
			$ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']));
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			//to check ip is pass from proxy
			$ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
		}

		// Fix potential CSV returned from $_SERVER variables
		$ip_array = explode( ',', $ip );
		$ip_array = array_map( 'trim', $ip_array );

		return $ip_array[0];
	}

	/**
	 * Emotional power words
	 *
	 * @return array emotional power words
	 */
	function emotion_power_words() {
		if ( isset( $this->emotion_power_words ) && ! empty( $this->emotion_power_words ) ) {
			return $this->emotion_power_words;
		}

		$this->emotion_power_words = array(
			__( "destroy", "google-analytics-premium" ),
			__( "extra", "google-analytics-premium" ),
			__( "in a", "google-analytics-premium" ),
			__( "devastating", "google-analytics-premium" ),
			__( "eye-opening", "google-analytics-premium" ),
			__( "gift", "google-analytics-premium" ),
			__( "in the world", "google-analytics-premium" ),
			__( "devoted", "google-analytics-premium" ),
			__( "fail", "google-analytics-premium" ),
			__( "in the", "google-analytics-premium" ),
			__( "faith", "google-analytics-premium" ),
			__( "grateful", "google-analytics-premium" ),
			__( "inexpensive", "google-analytics-premium" ),
			__( "dirty", "google-analytics-premium" ),
			__( "famous", "google-analytics-premium" ),
			__( "disastrous", "google-analytics-premium" ),
			__( "fantastic", "google-analytics-premium" ),
			__( "greed", "google-analytics-premium" ),
			__( "grit", "google-analytics-premium" ),
			__( "insanely", "google-analytics-premium" ),
			__( "disgusting", "google-analytics-premium" ),
			__( "fearless", "google-analytics-premium" ),
			__( "disinformation", "google-analytics-premium" ),
			__( "feast", "google-analytics-premium" ),
			__( "insidious", "google-analytics-premium" ),
			__( "dollar", "google-analytics-premium" ),
			__( "feeble", "google-analytics-premium" ),
			__( "gullible", "google-analytics-premium" ),
			__( "double", "google-analytics-premium" ),
			__( "fire", "google-analytics-premium" ),
			__( "hack", "google-analytics-premium" ),
			__( "fleece", "google-analytics-premium" ),
			__( "had enough", "google-analytics-premium" ),
			__( "invasion", "google-analytics-premium" ),
			__( "drowning", "google-analytics-premium" ),
			__( "floundering", "google-analytics-premium" ),
			__( "happy", "google-analytics-premium" ),
			__( "ironclad", "google-analytics-premium" ),
			__( "dumb", "google-analytics-premium" ),
			__( "flush", "google-analytics-premium" ),
			__( "hate", "google-analytics-premium" ),
			__( "irresistibly", "google-analytics-premium" ),
			__( "hazardous", "google-analytics-premium" ),
			__( "is the", "google-analytics-premium" ),
			__( "fool", "google-analytics-premium" ),
			__( "is what happens when", "google-analytics-premium" ),
			__( "fooled", "google-analytics-premium" ),
			__( "helpless", "google-analytics-premium" ),
			__( "it looks like a", "google-analytics-premium" ),
			__( "embarrass", "google-analytics-premium" ),
			__( "for the first time", "google-analytics-premium" ),
			__( "help are the", "google-analytics-premium" ),
			__( "jackpot", "google-analytics-premium" ),
			__( "forbidden", "google-analytics-premium" ),
			__( "hidden", "google-analytics-premium" ),
			__( "jail", "google-analytics-premium" ),
			__( "empower", "google-analytics-premium" ),
			__( "force-fed", "google-analytics-premium" ),
			__( "high", "google-analytics-premium" ),
			__( "jaw-dropping", "google-analytics-premium" ),
			__( "forgotten", "google-analytics-premium" ),
			__( "jeopardy", "google-analytics-premium" ),
			__( "energize", "google-analytics-premium" ),
			__( "hoax", "google-analytics-premium" ),
			__( "jubilant", "google-analytics-premium" ),
			__( "foul", "google-analytics-premium" ),
			__( "hope", "google-analytics-premium" ),
			__( "killer", "google-analytics-premium" ),
			__( "frantic", "google-analytics-premium" ),
			__( "horrific", "google-analytics-premium" ),
			__( "know it all", "google-analytics-premium" ),
			__( "epic", "google-analytics-premium" ),
			__( "how to make", "google-analytics-premium" ),
			__( "evil", "google-analytics-premium" ),
			__( "freebie", "google-analytics-premium" ),
			__( "frenzy", "google-analytics-premium" ),
			__( "hurricane", "google-analytics-premium" ),
			__( "excited", "google-analytics-premium" ),
			__( "fresh on the mind", "google-analytics-premium" ),
			__( "frightening", "google-analytics-premium" ),
			__( "hypnotic", "google-analytics-premium" ),
			__( "lawsuit", "google-analytics-premium" ),
			__( "frugal", "google-analytics-premium" ),
			__( "illegal", "google-analytics-premium" ),
			__( "fulfill", "google-analytics-premium" ),
			__( "lick", "google-analytics-premium" ),
			__( "explode", "google-analytics-premium" ),
			__( "lies", "google-analytics-premium" ),
			__( "exposed", "google-analytics-premium" ),
			__( "gambling", "google-analytics-premium" ),
			__( "like a normal", "google-analytics-premium" ),
			__( "nightmare", "google-analytics-premium" ),
			__( "results", "google-analytics-premium" ),
			__( "line", "google-analytics-premium" ),
			__( "no good", "google-analytics-premium" ),
			__( "pound", "google-analytics-premium" ),
			__( "loathsome", "google-analytics-premium" ),
			__( "no questions asked", "google-analytics-premium" ),
			__( "revenge", "google-analytics-premium" ),
			__( "lonely", "google-analytics-premium" ),
			__( "looks like a", "google-analytics-premium" ),
			__( "obnoxious", "google-analytics-premium" ),
			__( "preposterous", "google-analytics-premium" ),
			__( "revolting", "google-analytics-premium" ),
			__( "looming", "google-analytics-premium" ),
			__( "priced", "google-analytics-premium" ),
			__( "lost", "google-analytics-premium" ),
			__( "prison", "google-analytics-premium" ),
			__( "lowest", "google-analytics-premium" ),
			__( "of the", "google-analytics-premium" ),
			__( "privacy", "google-analytics-premium" ),
			__( "rich", "google-analytics-premium" ),
			__( "lunatic", "google-analytics-premium" ),
			__( "off-limits", "google-analytics-premium" ),
			__( "private", "google-analytics-premium" ),
			__( "risky", "google-analytics-premium" ),
			__( "lurking", "google-analytics-premium" ),
			__( "offer", "google-analytics-premium" ),
			__( "prize", "google-analytics-premium" ),
			__( "ruthless", "google-analytics-premium" ),
			__( "lust", "google-analytics-premium" ),
			__( "official", "google-analytics-premium" ),
			__( "luxurious", "google-analytics-premium" ),
			__( "on the", "google-analytics-premium" ),
			__( "profit", "google-analytics-premium" ),
			__( "scary", "google-analytics-premium" ),
			__( "lying", "google-analytics-premium" ),
			__( "outlawed", "google-analytics-premium" ),
			__( "protected", "google-analytics-premium" ),
			__( "scream", "google-analytics-premium" ),
			__( "searing", "google-analytics-premium" ),
			__( "overcome", "google-analytics-premium" ),
			__( "provocative", "google-analytics-premium" ),
			__( "make you", "google-analytics-premium" ),
			__( "painful", "google-analytics-premium" ),
			__( "pummel", "google-analytics-premium" ),
			__( "secure", "google-analytics-premium" ),
			__( "pale", "google-analytics-premium" ),
			__( "punish", "google-analytics-premium" ),
			__( "marked down", "google-analytics-premium" ),
			__( "panic", "google-analytics-premium" ),
			__( "quadruple", "google-analytics-premium" ),
			__( "secutively", "google-analytics-premium" ),
			__( "massive", "google-analytics-premium" ),
			__( "pay zero", "google-analytics-premium" ),
			__( "seize", "google-analytics-premium" ),
			__( "meltdown", "google-analytics-premium" ),
			__( "payback", "google-analytics-premium" ),
			__( "might look like a", "google-analytics-premium" ),
			__( "peril", "google-analytics-premium" ),
			__( "mind-blowing", "google-analytics-premium" ),
			__( "shameless", "google-analytics-premium" ),
			__( "minute", "google-analytics-premium" ),
			__( "rave", "google-analytics-premium" ),
			__( "shatter", "google-analytics-premium" ),
			__( "piranha", "google-analytics-premium" ),
			__( "reckoning", "google-analytics-premium" ),
			__( "shellacking", "google-analytics-premium" ),
			__( "mired", "google-analytics-premium" ),
			__( "pitfall", "google-analytics-premium" ),
			__( "reclaim", "google-analytics-premium" ),
			__( "mistakes", "google-analytics-premium" ),
			__( "plague", "google-analytics-premium" ),
			__( "sick and tired", "google-analytics-premium" ),
			__( "money", "google-analytics-premium" ),
			__( "played", "google-analytics-premium" ),
			__( "refugee", "google-analytics-premium" ),
			__( "silly", "google-analytics-premium" ),
			__( "money-grubbing", "google-analytics-premium" ),
			__( "pluck", "google-analytics-premium" ),
			__( "refund", "google-analytics-premium" ),
			__( "moneyback", "google-analytics-premium" ),
			__( "plummet", "google-analytics-premium" ),
			__( "plunge", "google-analytics-premium" ),
			__( "murder", "google-analytics-premium" ),
			__( "pointless", "google-analytics-premium" ),
			__( "sinful", "google-analytics-premium" ),
			__( "myths", "google-analytics-premium" ),
			__( "poor", "google-analytics-premium" ),
			__( "remarkably", "google-analytics-premium" ),
			__( "six-figure", "google-analytics-premium" ),
			__( "never again", "google-analytics-premium" ),
			__( "research", "google-analytics-premium" ),
			__( "surrender", "google-analytics-premium" ),
			__( "to the", "google-analytics-premium" ),
			__( "varify", "google-analytics-premium" ),
			__( "skyrocket", "google-analytics-premium" ),
			__( "toxic", "google-analytics-premium" ),
			__( "vibrant", "google-analytics-premium" ),
			__( "slaughter", "google-analytics-premium" ),
			__( "swindle", "google-analytics-premium" ),
			__( "trap", "google-analytics-premium" ),
			__( "victim", "google-analytics-premium" ),
			__( "sleazy", "google-analytics-premium" ),
			__( "taboo", "google-analytics-premium" ),
			__( "treasure", "google-analytics-premium" ),
			__( "victory", "google-analytics-premium" ),
			__( "smash", "google-analytics-premium" ),
			__( "tailspin", "google-analytics-premium" ),
			__( "vindication", "google-analytics-premium" ),
			__( "smug", "google-analytics-premium" ),
			__( "tank", "google-analytics-premium" ),
			__( "triple", "google-analytics-premium" ),
			__( "viral", "google-analytics-premium" ),
			__( "smuggled", "google-analytics-premium" ),
			__( "tantalizing", "google-analytics-premium" ),
			__( "triumph", "google-analytics-premium" ),
			__( "volatile", "google-analytics-premium" ),
			__( "sniveling", "google-analytics-premium" ),
			__( "targeted", "google-analytics-premium" ),
			__( "truth", "google-analytics-premium" ),
			__( "vulnerable", "google-analytics-premium" ),
			__( "snob", "google-analytics-premium" ),
			__( "tawdry", "google-analytics-premium" ),
			__( "try before you buy", "google-analytics-premium" ),
			__( "tech", "google-analytics-premium" ),
			__( "turn the tables", "google-analytics-premium" ),
			__( "wanton", "google-analytics-premium" ),
			__( "soaring", "google-analytics-premium" ),
			__( "warning", "google-analytics-premium" ),
			__( "teetering", "google-analytics-premium" ),
			__( "unauthorized", "google-analytics-premium" ),
			__( "spectacular", "google-analytics-premium" ),
			__( "temporary fix", "google-analytics-premium" ),
			__( "unbelievably", "google-analytics-premium" ),
			__( "spine", "google-analytics-premium" ),
			__( "tempting", "google-analytics-premium" ),
			__( "uncommonly", "google-analytics-premium" ),
			__( "what happened", "google-analytics-premium" ),
			__( "spirit", "google-analytics-premium" ),
			__( "what happens when", "google-analytics-premium" ),
			__( "terror", "google-analytics-premium" ),
			__( "under", "google-analytics-premium" ),
			__( "what happens", "google-analytics-premium" ),
			__( "staggering", "google-analytics-premium" ),
			__( "underhanded", "google-analytics-premium" ),
			__( "what this", "google-analytics-premium" ),
			__( "that will make you", "google-analytics-premium" ),
			__( "undo", "when you see", "google-analytics-premium" ),
			__( "that will make", "google-analytics-premium" ),
			__( "unexpected", "google-analytics-premium" ),
			__( "when you", "google-analytics-premium" ),
			__( "strangle", "google-analytics-premium" ),
			__( "that will", "google-analytics-premium" ),
			__( "whip", "google-analytics-premium" ),
			__( "the best", "google-analytics-premium" ),
			__( "whopping", "google-analytics-premium" ),
			__( "stuck up", "google-analytics-premium" ),
			__( "the ranking of", "google-analytics-premium" ),
			__( "wicked", "google-analytics-premium" ),
			__( "stunning", "google-analytics-premium" ),
			__( "the most", "google-analytics-premium" ),
			__( "will make you", "google-analytics-premium" ),
			__( "stupid", "google-analytics-premium" ),
			__( "the reason why is", "google-analytics-premium" ),
			__( "unscrupulous", "google-analytics-premium" ),
			__( "thing ive ever seen", "google-analytics-premium" ),
			__( "withheld", "google-analytics-premium" ),
			__( "this is the", "google-analytics-premium" ),
			__( "this is what happens", "google-analytics-premium" ),
			__( "unusually", "google-analytics-premium" ),
			__( "wondrous", "google-analytics-premium" ),
			__( "this is what", "google-analytics-premium" ),
			__( "uplifting", "google-analytics-premium" ),
			__( "worry", "google-analytics-premium" ),
			__( "sure", "google-analytics-premium" ),
			__( "this is", "google-analytics-premium" ),
			__( "wounded", "google-analytics-premium" ),
			__( "surge", "google-analytics-premium" ),
			__( "thrilled", "google-analytics-premium" ),
			__( "you need to know", "google-analytics-premium" ),
			__( "thrilling", "google-analytics-premium" ),
			__( "valor", "google-analytics-premium" ),
			__( "you need to", "google-analytics-premium" ),
			__( "you see what", "google-analytics-premium" ),
			__( "surprising", "google-analytics-premium" ),
			__( "tired", "google-analytics-premium" ),
			__( "you see", "google-analytics-premium" ),
			__( "surprisingly", "google-analytics-premium" ),
			__( "to be", "google-analytics-premium" ),
			__( "vaporize", "google-analytics-premium" ),
		);

		return $this->emotion_power_words;
	}

	/**
	 * Power words
	 *
	 * @return array power words
	 */
	function power_words() {
		if ( isset( $this->power_words ) && ! empty( $this->power_words ) ) {
			return $this->power_words;
		}

		$this->power_words = array(
			__( "great", "google-analytics-premium" ),
			__( "free", "google-analytics-premium" ),
			__( "focus", "google-analytics-premium" ),
			__( "remarkable", "google-analytics-premium" ),
			__( "confidential", "google-analytics-premium" ),
			__( "sale", "google-analytics-premium" ),
			__( "wanted", "google-analytics-premium" ),
			__( "obsession", "google-analytics-premium" ),
			__( "sizable", "google-analytics-premium" ),
			__( "new", "google-analytics-premium" ),
			__( "absolutely lowest", "google-analytics-premium" ),
			__( "surging", "google-analytics-premium" ),
			__( "wonderful", "google-analytics-premium" ),
			__( "professional", "google-analytics-premium" ),
			__( "interesting", "google-analytics-premium" ),
			__( "revisited", "google-analytics-premium" ),
			__( "delivered", "google-analytics-premium" ),
			__( "guaranteed", "google-analytics-premium" ),
			__( "challenge", "google-analytics-premium" ),
			__( "unique", "google-analytics-premium" ),
			__( "secrets", "google-analytics-premium" ),
			__( "special", "google-analytics-premium" ),
			__( "lifetime", "google-analytics-premium" ),
			__( "bargain", "google-analytics-premium" ),
			__( "scarce", "google-analytics-premium" ),
			__( "tested", "google-analytics-premium" ),
			__( "highest", "google-analytics-premium" ),
			__( "hurry", "google-analytics-premium" ),
			__( "alert famous", "google-analytics-premium" ),
			__( "improved", "google-analytics-premium" ),
			__( "expert", "google-analytics-premium" ),
			__( "daring", "google-analytics-premium" ),
			__( "strong", "google-analytics-premium" ),
			__( "immediately", "google-analytics-premium" ),
			__( "advice", "google-analytics-premium" ),
			__( "pioneering", "google-analytics-premium" ),
			__( "unusual", "google-analytics-premium" ),
			__( "limited", "google-analytics-premium" ),
			__( "the truth about", "google-analytics-premium" ),
			__( "destiny", "google-analytics-premium" ),
			__( "outstanding", "google-analytics-premium" ),
			__( "simplistic", "google-analytics-premium" ),
			__( "compare", "google-analytics-premium" ),
			__( "unsurpassed", "google-analytics-premium" ),
			__( "energy", "google-analytics-premium" ),
			__( "powerful", "google-analytics-premium" ),
			__( "colorful", "google-analytics-premium" ),
			__( "genuine", "google-analytics-premium" ),
			__( "instructive", "google-analytics-premium" ),
			__( "big", "google-analytics-premium" ),
			__( "affordable", "google-analytics-premium" ),
			__( "informative", "google-analytics-premium" ),
			__( "liberal", "google-analytics-premium" ),
			__( "popular", "google-analytics-premium" ),
			__( "ultimate", "google-analytics-premium" ),
			__( "mainstream", "google-analytics-premium" ),
			__( "rare", "google-analytics-premium" ),
			__( "exclusive", "google-analytics-premium" ),
			__( "willpower", "google-analytics-premium" ),
			__( "complete", "google-analytics-premium" ),
			__( "edge", "google-analytics-premium" ),
			__( "valuable", "google-analytics-premium" ),
			__( "attractive", "google-analytics-premium" ),
			__( "last chance", "google-analytics-premium" ),
			__( "superior", "google-analytics-premium" ),
			__( "how to", "google-analytics-premium" ),
			__( "easily", "google-analytics-premium" ),
			__( "exploit", "google-analytics-premium" ),
			__( "unparalleled", "google-analytics-premium" ),
			__( "endorsed", "google-analytics-premium" ),
			__( "approved", "google-analytics-premium" ),
			__( "quality", "google-analytics-premium" ),
			__( "fascinating", "google-analytics-premium" ),
			__( "unlimited", "google-analytics-premium" ),
			__( "competitive", "google-analytics-premium" ),
			__( "gigantic", "google-analytics-premium" ),
			__( "compromise", "google-analytics-premium" ),
			__( "discount", "google-analytics-premium" ),
			__( "full", "google-analytics-premium" ),
			__( "love", "google-analytics-premium" ),
			__( "odd", "google-analytics-premium" ),
			__( "fundamentals", "google-analytics-premium" ),
			__( "mammoth", "google-analytics-premium" ),
			__( "lavishly", "google-analytics-premium" ),
			__( "bottom line", "google-analytics-premium" ),
			__( "under priced", "google-analytics-premium" ),
			__( "innovative", "google-analytics-premium" ),
			__( "reliable", "google-analytics-premium" ),
			__( "zinger", "google-analytics-premium" ),
			__( "suddenly", "google-analytics-premium" ),
			__( "it's here", "google-analytics-premium" ),
			__( "terrific", "google-analytics-premium" ),
			__( "simplified", "google-analytics-premium" ),
			__( "perspective", "google-analytics-premium" ),
			__( "just arrived", "google-analytics-premium" ),
			__( "breakthrough", "google-analytics-premium" ),
			__( "tremendous", "google-analytics-premium" ),
			__( "launching", "google-analytics-premium" ),
			__( "sure fire", "google-analytics-premium" ),
			__( "emerging", "google-analytics-premium" ),
			__( "helpful", "google-analytics-premium" ),
			__( "skill", "google-analytics-premium" ),
			__( "soar", "google-analytics-premium" ),
			__( "profitable", "google-analytics-premium" ),
			__( "special offer", "google-analytics-premium" ),
			__( "reduced", "google-analytics-premium" ),
			__( "beautiful", "google-analytics-premium" ),
			__( "sampler", "google-analytics-premium" ),
			__( "technology", "google-analytics-premium" ),
			__( "better", "google-analytics-premium" ),
			__( "crammed", "google-analytics-premium" ),
			__( "noted", "google-analytics-premium" ),
			__( "selected", "google-analytics-premium" ),
			__( "shrewd", "google-analytics-premium" ),
			__( "growth", "google-analytics-premium" ),
			__( "luxury", "google-analytics-premium" ),
			__( "sturdy", "google-analytics-premium" ),
			__( "enormous", "google-analytics-premium" ),
			__( "promising", "google-analytics-premium" ),
			__( "unconditional", "google-analytics-premium" ),
			__( "wealth", "google-analytics-premium" ),
			__( "spotlight", "google-analytics-premium" ),
			__( "astonishing", "google-analytics-premium" ),
			__( "timely", "google-analytics-premium" ),
			__( "successful", "google-analytics-premium" ),
			__( "useful", "google-analytics-premium" ),
			__( "imagination", "google-analytics-premium" ),
			__( "bonanza", "google-analytics-premium" ),
			__( "opportunities", "google-analytics-premium" ),
			__( "survival", "google-analytics-premium" ),
			__( "greatest", "google-analytics-premium" ),
			__( "security", "google-analytics-premium" ),
			__( "last minute", "google-analytics-premium" ),
			__( "largest", "google-analytics-premium" ),
			__( "high tech", "google-analytics-premium" ),
			__( "refundable", "google-analytics-premium" ),
			__( "monumental", "google-analytics-premium" ),
			__( "colossal", "google-analytics-premium" ),
			__( "latest", "google-analytics-premium" ),
			__( "quickly", "google-analytics-premium" ),
			__( "startling", "google-analytics-premium" ),
			__( "now", "google-analytics-premium" ),
			__( "important", "google-analytics-premium" ),
			__( "revolutionary", "google-analytics-premium" ),
			__( "quick", "google-analytics-premium" ),
			__( "unlock", "google-analytics-premium" ),
			__( "urgent", "google-analytics-premium" ),
			__( "miracle", "google-analytics-premium" ),
			__( "easy", "google-analytics-premium" ),
			__( "fortune", "google-analytics-premium" ),
			__( "amazing", "google-analytics-premium" ),
			__( "magic", "google-analytics-premium" ),
			__( "direct", "google-analytics-premium" ),
			__( "authentic", "google-analytics-premium" ),
			__( "exciting", "google-analytics-premium" ),
			__( "proven", "google-analytics-premium" ),
			__( "simple", "google-analytics-premium" ),
			__( "announcing", "google-analytics-premium" ),
			__( "portfolio", "google-analytics-premium" ),
			__( "reward", "google-analytics-premium" ),
			__( "strange", "google-analytics-premium" ),
			__( "huge gift", "google-analytics-premium" ),
			__( "revealing", "google-analytics-premium" ),
			__( "weird", "google-analytics-premium" ),
			__( "value", "google-analytics-premium" ),
			__( "introducing", "google-analytics-premium" ),
			__( "sensational", "google-analytics-premium" ),
			__( "surprise", "google-analytics-premium" ),
			__( "insider", "google-analytics-premium" ),
			__( "practical", "google-analytics-premium" ),
			__( "excellent", "google-analytics-premium" ),
			__( "delighted", "google-analytics-premium" ),
			__( "download", "google-analytics-premium" ),
		);

		return $this->power_words;
	}

	/**
	 * Common words
	 *
	 * @return array common words
	 */
	function common_words() {
		if ( isset( $this->common_words ) && ! empty( $this->common_words ) ) {
			return $this->common_words;
		}

		$this->common_words = array(
			__( "a", "google-analytics-premium" ),
			__( "for", "google-analytics-premium" ),
			__( "about", "google-analytics-premium" ),
			__( "from", "google-analytics-premium" ),
			__( "after", "google-analytics-premium" ),
			__( "get", "google-analytics-premium" ),
			__( "all", "google-analytics-premium" ),
			__( "has", "google-analytics-premium" ),
			__( "an", "google-analytics-premium" ),
			__( "have", "google-analytics-premium" ),
			__( "and", "google-analytics-premium" ),
			__( "he", "google-analytics-premium" ),
			__( "are", "google-analytics-premium" ),
			__( "her", "google-analytics-premium" ),
			__( "as", "google-analytics-premium" ),
			__( "his", "google-analytics-premium" ),
			__( "at", "google-analytics-premium" ),
			__( "how", "google-analytics-premium" ),
			__( "be", "google-analytics-premium" ),
			__( "I", "google-analytics-premium" ),
			__( "but", "google-analytics-premium" ),
			__( "if", "google-analytics-premium" ),
			__( "by", "google-analytics-premium" ),
			__( "in", "google-analytics-premium" ),
			__( "can", "google-analytics-premium" ),
			__( "is", "google-analytics-premium" ),
			__( "did", "google-analytics-premium" ),
			__( "it", "google-analytics-premium" ),
			__( "do", "google-analytics-premium" ),
			__( "just", "google-analytics-premium" ),
			__( "ever", "google-analytics-premium" ),
			__( "like", "google-analytics-premium" ),
			__( "ll", "google-analytics-premium" ),
			__( "these", "google-analytics-premium" ),
			__( "me", "google-analytics-premium" ),
			__( "they", "google-analytics-premium" ),
			__( "most", "google-analytics-premium" ),
			__( "things", "google-analytics-premium" ),
			__( "my", "google-analytics-premium" ),
			__( "this", "google-analytics-premium" ),
			__( "no", "google-analytics-premium" ),
			__( "to", "google-analytics-premium" ),
			__( "not", "google-analytics-premium" ),
			__( "up", "google-analytics-premium" ),
			__( "of", "google-analytics-premium" ),
			__( "was", "google-analytics-premium" ),
			__( "on", "google-analytics-premium" ),
			__( "what", "google-analytics-premium" ),
			__( "re", "google-analytics-premium" ),
			__( "when", "google-analytics-premium" ),
			__( "she", "google-analytics-premium" ),
			__( "who", "google-analytics-premium" ),
			__( "sould", "google-analytics-premium" ),
			__( "why", "google-analytics-premium" ),
			__( "so", "google-analytics-premium" ),
			__( "will", "google-analytics-premium" ),
			__( "that", "google-analytics-premium" ),
			__( "with", "google-analytics-premium" ),
			__( "the", "google-analytics-premium" ),
			__( "you", "google-analytics-premium" ),
			__( "their", "google-analytics-premium" ),
			__( "your", "google-analytics-premium" ),
			__( "there", "google-analytics-premium" ),
		);

		return $this->common_words;
	}


	/**
	 * Uncommon words
	 *
	 * @return array uncommon words
	 */
	function uncommon_words() {
		if ( isset( $this->uncommon_words ) && ! empty( $this->uncommon_words ) ) {
			return $this->uncommon_words;
		}

		$this->uncommon_words = array(
			__( "actually", "google-analytics-premium" ),
			__( "happened", "google-analytics-premium" ),
			__( "need", "google-analytics-premium" ),
			__( "thing", "google-analytics-premium" ),
			__( "awesome", "google-analytics-premium" ),
			__( "heart", "google-analytics-premium" ),
			__( "never", "google-analytics-premium" ),
			__( "think", "google-analytics-premium" ),
			__( "baby", "google-analytics-premium" ),
			__( "here", "google-analytics-premium" ),
			__( "new", "google-analytics-premium" ),
			__( "time", "google-analytics-premium" ),
			__( "beautiful", "google-analytics-premium" ),
			__( "its", "google-analytics-premium" ),
			__( "now", "google-analytics-premium" ),
			__( "valentines", "google-analytics-premium" ),
			__( "being", "google-analytics-premium" ),
			__( "know", "google-analytics-premium" ),
			__( "old", "google-analytics-premium" ),
			__( "video", "google-analytics-premium" ),
			__( "best", "google-analytics-premium" ),
			__( "life", "google-analytics-premium" ),
			__( "one", "google-analytics-premium" ),
			__( "want", "google-analytics-premium" ),
			__( "better", "google-analytics-premium" ),
			__( "little", "google-analytics-premium" ),
			__( "out", "google-analytics-premium" ),
			__( "watch", "google-analytics-premium" ),
			__( "boy", "google-analytics-premium" ),
			__( "look", "google-analytics-premium" ),
			__( "people", "google-analytics-premium" ),
			__( "way", "google-analytics-premium" ),
			__( "dog", "google-analytics-premium" ),
			__( "love", "google-analytics-premium" ),
			__( "photos", "google-analytics-premium" ),
			__( "ways", "google-analytics-premium" ),
			__( "down", "google-analytics-premium" ),
			__( "made", "google-analytics-premium" ),
			__( "really", "google-analytics-premium" ),
			__( "world", "google-analytics-premium" ),
			__( "facebook", "google-analytics-premium" ),
			__( "make", "google-analytics-premium" ),
			__( "reasons", "google-analytics-premium" ),
			__( "year", "google-analytics-premium" ),
			__( "first", "google-analytics-premium" ),
			__( "makes", "google-analytics-premium" ),
			__( "right", "google-analytics-premium" ),
			__( "years", "google-analytics-premium" ),
			__( "found", "google-analytics-premium" ),
			__( "man", "google-analytics-premium" ),
			__( "see", "google-analytics-premium" ),
			__( "you'll", "google-analytics-premium" ),
			__( "girl", "google-analytics-premium" ),
			__( "media", "google-analytics-premium" ),
			__( "seen", "google-analytics-premium" ),
			__( "good", "google-analytics-premium" ),
			__( "mind", "google-analytics-premium" ),
			__( "social", "google-analytics-premium" ),
			__( "guy", "google-analytics-premium" ),
			__( "more", "google-analytics-premium" ),
			__( "something", "google-analytics-premium" ),
		);

		return $this->uncommon_words;
	}
}

new MonsterInsightsHeadlineToolPlugin();
