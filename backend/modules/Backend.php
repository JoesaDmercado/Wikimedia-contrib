<?php
require_once( '__config__.php' );
require_once( 'Base.php' );
require_once( 'KLogger.php' );
require_once( 'Logger.php' );
require_once( 'Cacher.php' );
require_once( 'Database.php' );
require_once( 'Wikimedia.php' );

/**
 * Provides a wrapper used by page scripts to generate HTML, interact
 * with the database, and so forth.
 */
class Backend extends Base {
	/*########
	## Properties
	########*/
	/*
	 * @var string The current page's filename, like "index.php".
	 */
	private $filename  = NULL;
	
	/**
	 * @var string The page title, usually the name of the script.
	 */
	private $title     = NULL;
	private $blurb     = NULL; // a short description displayed at the top of the page; defaults to nothing.
	private $hook_head = NULL; // extra content to insert into HTML <head>
	
	public $logger = NULL;
	public $cache = NULL;
	public $db = NULL;
	public $wikimedia = NULL;

	#################################################
	## Constructor
	#################################################
	public function __construct( $title, $blurb ) {
		parent::__construct();
		
		/* get configuration */
		global $gconfig;
		$this->config = &$gconfig;
		
		/* handle options */
		$this->filename = basename( $_SERVER['SCRIPT_NAME'] );
		$this->title    = isset($title) ? $title : $this->filename;
		$this->blurb    = isset($blurb) ? $blurb : NULL;
		$this->license  = $gconfig['license'];
		$this->scripts = NULL;
		
		/* start logger */
		$key = hash('crc32b', $_SERVER['REQUEST_TIME'] . $_SERVER['HTTP_X_FORWARDED_FOR'] . $_SERVER['REQUEST_URI']);
		$this->logger = new Logger('/home/pathoschild/logs', $key);
		$this->logger->log('request: [' . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . '] by [' . $_SERVER['HTTP_X_FORWARDED_FOR'] . ' ' . $_SERVER['HTTP_USER_AGENT'] . ']');
		
		/* build cache */
		$this->cache = new Cacher('/home/pathoschild/public_html/backend/modules/cache/', $this->logger, !!$this->get('purge'));
	}
	public static function create($title, $blurb) {
		return new Backend($title, $blurb);
	}


	#################################################
	## Objects
	#################################################
	public function GetDatabase($options = NULL) {
		if(!$this->db)
			$this->db = new Toolserver($this->logger, $this->cache, $options);
		return $this->db;
	}
		

	#################################################
	## HTTP encapsulation
	#################################################
	#############################
	## Get a value from the HTTP GET values.
	#############################
	public function get( $name, $default = NULL ) {
		if(isset($_GET[$name]) && $_GET[$name] != '')
			return $_GET[$name];
		return $default;
	}


	#############################
	## Link to external files in the header
	#############################
	public function link( $url ) {
		$ext = substr( $url, -3 );
		switch( $ext ) {
			case 'css':
				$this->hook_head .= '<link rel="stylesheet" type="text/css" href="' . $url . '" />';
				break;
			case '.js':
				$this->hook_head .= '<script type="text/javascript" src="' . $url . '"></script>';
				break;
			default:
				die( "Invalid extension '{$ext}' (URL '{$url}') passed to Backend->link." );
		}
		
		return $this;
	}
	
	public function addScript( $script ) {
		$this->hook_head .= '<script type="text/javascript">' . "\n$script\n" . '</script>';
		return $this;
	}


	#############################
	## Print header
	#############################
	public function trackWithoutHtml($outlink = null) {
		require_once( __DIR__.'/external/PiwikTracker.php' );
	
		PiwikTracker::$URL = 'http://toolserver.org/~pathoschild/backend/piwik';
		$tracker = new PiwikTracker($idSite = 1);
		$tracker->setCustomVariable(1, 'tracked-without-html', 1);
		$tracker->setTokenAuth($PIWIK_AUTH_TOKEN);
		$tracker->setIp($_SERVER['HTTP_X_FORWARDED_FOR']);
		#$tracker->doTrackPageView($this->title);
		$tracker->doTrackAction('wut', 'download');
		if($outlink)
			$tracker->doTrackAction($outlink, 'link');
		
		echo '<pre>', print_r($tracker, true), '</pre>';
	}
	
	public function header() {
		/* print document head */
		echo '
<!-- begin generated header -->
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title>', $this->title, '</title>
		<link rel="shortcut icon" href="', $this->config['style_url'], 'favicon.ico" />
		<link rel="stylesheet" type="text/css" href="', $this->config['style_url'], 'stylesheet.css?v=20120222" />
		<script src="//ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js" type="text/javascript"></script>
		', $this->hook_head, '
		<script src="//toolserver.org/~pathoschild/backend/piwik/piwik.js" type="text/javascript"></script>
	</head>
	<body>
		<div id="sidebar">
		<h4>Pathoschild\'s tools</h4>';
		
		/* print navigation menu */
		foreach( $this->config['tools'] as $section => $links ) {
			echo '
			<h5>', $section, '</h5>
			<ul>';
			
			foreach($links as $link) {
				$title = $link[0];
				$desc  = isset( $link[1] ) ? $link[1] : '';
				$desc  = str_replace( '\'', '&#38;', $desc ); 
				$url   = isset( $link[2] ) ? $link[2] : $this->config['root_url'];
				$url  .= $this->strip_nonlatin( str_replace(' ', '', $title) );
				
				echo '<li><a href="', $url, '" title="', $desc, '"',
					(isset($link[2]) ? ' class="is-legacy"' : ''),
					'>', $title, '</a></li>';
			}
			echo '</ul>';
		}
		
		/* print content head */
		echo '
		</div>
		<div id="content-column">
			<div id="content">';
		include('/home/pathoschild/public_html/backend/notice.php');
		echo '<h1>', $this->title, '<sup>beta</sup></h1>
				<p id="blurb">', $this->blurb, '</p>';
		echo '
<!-- end generated header -->';
		return $this;
	}
	
	#############################
	## Print footer
	#############################
	public function footer() {
		/* generate benchmarks */
		$precisionPercentage = $this->config['profile_perc_precision'];
		$precisionTime = $this->config['profile_time_precision'];
		$totalTime = $this->TimerGetElapsedSinceStart();
		$timerResults = array();
		foreach( $this->TimerGetKeys() as $key )
		{
			$time = $this->TimerGetElapsed($key);
			$this->_footer_benchmarks[$key] = sprintf(
				"%s (%s%%)",
				round($time, $precisionTime),
				round($time / $totalTime * 100, $precisionPercentage)
			);
		}
		$resultSeconds = round( $totalTime, $precisionTime );
		$this->logger->log('completed: ' . $resultSeconds . ' seconds.');
//		
		/* output */
		echo '
<!-- begin generated footer -->
			</div>
			<p id="license">
				Hi! You can <a href="https://github.com/Pathoschild/Wikimedia-contrib.toolserver" title="view source">view the source code</a> or <a href="https://github.com/Pathoschild/Wikimedia-contrib.toolserver/issues" title="report issue">report a bug or suggestion</a>. ', $this->license, '<br />
				Page generated in ', $resultSeconds, ' seconds.
		';
		
		if(count($timerResults)) {
			echo '<br />
				Benchmarks:<br />
			';
			foreach( $timerResults as $name => $time ) {
				echo '
					&emsp;&emsp;', $name, ': ', $time, '<br />';
			}
			echo '
				</table>';
		}
		
		echo '
			</p>
		</div>
	
		<script type="text/javascript">
			try {
				var piwikTracker = Piwik.getTracker(\'//toolserver.org/~pathoschild/backend/piwik/piwik.php\', 1);
				piwikTracker.trackPageView();
				piwikTracker.enableLinkTracking();
			} catch( err ) {}
		</script>
		<noscript>
			<img src="//toolserver.org/~pathoschild/backend/piwik/piwik.php?idsite=1&amp;rec=1&amp;urlref=', urlencode(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''), '" style="border:0" alt="" />
		</noscript>
	</body>
</html>';
	}
}
?>
