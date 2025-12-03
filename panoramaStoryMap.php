<?php

# Class to create a template application
class panoramaStoryMap extends frontControllerApplication
{
	# Function to assign defaults additional to the general application defaults
	public function defaults ()
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$defaults = array (
			'applicationName'		=> 'Panorama story map',
			'div'					=> strtolower (__CLASS__),
			'tabUlClass'			=> 'tabsflat',
			'databaseStrictWhere'	=> true,
			'nativeTypes'			=> true,
			'administrators'		=> 'administrators',
			'database'				=> 'panoramastorymap',
			'table'					=> 'scenes',
			'useTemplating'			=> true,
			'apiUsername'			=> true,
			'maptilerApiKey'		=> NULL,
			'assetWidth'			=> '100%',	// Or e.g. '260px'
			'flyTo'					=> array (
				'center'				=> array (-75, -23.32),
				'zoom'					=> 2.3,
				'pitch'					=> 0,
			),
			'minZoom'				=> 6,
		);
		
		# Return the defaults
		return $defaults;
	}
	
	
	# Function to assign supported actions
	public function actions ()
	{
		# Define available actions
		$actions = array (
			'about' => array (
				'description' => false,
				'url' => 'about/',
				'tab' => 'About',
			),
			'scene' => array (
				'description' => false,
				'tab' => NULL,
				'usetab' => 'home',
			),
			'edit' => array (
				'description' => false,
				'url' => 'edit/',
				'tab' => 'Scenes data',
				'icon' => 'pencil',
				'administrator'	=> true,
			),
			'regenerate' => array (
				'description' => 'Regenerate scene files',
				'url' => 'admin/regenerate.html',
				'subtab' => 'Regenerate scene files',
				'parent' => 'admin',
				'icon' => 'arrow_refresh',
				'administrator'	=> true,
			),
		);
		
		# Return the actions
		return $actions;
	}
	
	
	# Database structure definition
	public function databaseStructure ()
	{
		return "
			
			-- Administrators
			CREATE TABLE IF NOT EXISTS `administrators` (
			  `username` varchar(255) NOT NULL COMMENT 'Username' PRIMARY KEY,
			  `active` enum('','Yes','No') NOT NULL DEFAULT 'Yes' COMMENT 'Currently active?',
			  `privilege` enum('Administrator','Restricted administrator') NOT NULL DEFAULT 'Administrator' COMMENT 'Administrator level'
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='System administrators';
			
			-- Settings
			CREATE TABLE IF NOT EXISTS `settings` (
			  `id` int NOT NULL COMMENT 'Automatic key (ignored) PRIMARY KEY',
			  `introductionHtml` MEDIUMTEXT NOT NULL COMMENT 'Home page introduction',
			  `aboutHtml` MEDIUMTEXT NOT NULL COMMENT 'About page content'
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Settings';

			
			-- My scenes
			CREATE TABLE `scenes` (
			  `id` varchar(255) NOT NULL COMMENT 'Scene ID for URL',
			  `title` varchar(255) NOT NULL COMMENT 'Title',
			  `description` text COMMENT 'Description',
			  `lon` decimal(10,7) NOT NULL COMMENT 'Longitude',
			  `lat` decimal(10,8) NOT NULL COMMENT 'Latitude',
			  `sceneFile` varchar(255) NOT NULL COMMENT 'Scene .zip from Marzipano',
			  `assetsFile` varchar(255) NOT NULL COMMENT 'Assets .zip file',
			  `protected` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Password (if required) for this location',
			  `live` tinyint DEFAULT NULL COMMENT 'Live?',
			  PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Table of scenes';
		";
	}
	
	
	
	# Additional processing
	public function main ()
	{
		
	}
	
	
	
	# Home page
	public function home ()
	{
		# Set the introduction text
		$this->template['introductionHtml'] = $this->settings['introductionHtml'];
		
		# Set the map config
		$config = application::arrayFields ($this->settings, array ('maptilerApiKey', 'flyTo', 'minZoom'));
		$this->template['configJson'] = json_encode ($config, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		
		# Process the template
		$html = $this->templatise ();
		
		# Show the HTML
		echo $html;
	}
	
	
	# About page
	public function about ()
	{
		# Set the introduction text
		$this->template['aboutHtml'] = $this->settings['aboutHtml'];
		
		# Process the template
		$html = $this->templatise ();
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to serve a scene
	public function scene ()
	{
		# Ensure there is a scene ID
		if (!$this->item) {
			$this->template['page404'] = $this->page404 ();
			return false;
		}
		
		# Validate the scene ID
		if (!$scene = $this->databaseConnection->selectOne ($this->settings['database'], $this->settings['table'], array ('id' => $this->item))) {
			$this->template['page404'] = $this->page404 ();
			return false;
		}
		
		# Require password if protected
		if ($scene['protected']) {
			if (!$this->passwordForm ($scene['protected'], $this->template['passwordForm'])) {
				$html = $this->templatise ();
				echo $html;
				return false;
			}
		}
		
		# Clear the output buffer, in case of use of auto_prepend_file
		ob_end_clean ();
		
		# Get the file
		$file = $this->applicationRoot . '/scenes/' . $this->item . '/app-files/index.html';
		$this->template['sceneHtml'] = file_get_contents ($file);

		# Process the template
		$html = $this->templatise ();
		
		# Show the HTML
		echo $html;
		
		# End explicitly in case of use of auto_append_file
		exit;
	}
	
	
	# Password form
	public function passwordForm ($password, &$html = '')
	{
		# Create the form
		$form = new form (array (
			'display' => 'paragraphs',
			'displayRestrictions' => false,
			'requiredFieldIndicator' => false,
			'autofocus' => true,
			'formCompleteText' => false,
		));
		$form->password (array (
			'name' => 'password',
			'title' => 'This section requires a password - please enter it',
			'required' => true,
			'regexp' => '^' . preg_quote ($password, '/') . '$',
		));
		if (!$result = $form->process ()) {
			return false;
		}
		
		# Return success
		return true;
	}
	
	
	# Scenes editor
	public function edit ()
	{
		# Delegate to the standard function for editing
		$sinenomineExtraSettings = array (
			'tableUrlMoniker' => __FUNCTION__,
			'fieldFiltering' => false,
			'callback' => array (
				$this->settings['database'] => array (
					$this->settings['table'] => array ($this, 'processUploadedFiles_callback'),
				),
			),
			'int1ToCheckbox' => true,
		);
		$dataBindingAttributes = array (
			'id' => array ('prepend' => '/scenes/', 'append' => '/', 'regexp' => '^[-a-z0-9]+$', 'description' => 'Lower-case a-z, 0-9, hyphens only'),
			'description' => array ('rows' => 3, ),
			'sceneFile' =>  array ('directory' => $this->applicationRoot . '/scenes/', 'forcedFileName' => '%id', 'lowercaseExtension' => true, 'allowedExtensions' => array ('zip'), ),
			'assetsFile' => array ('directory' => $this->applicationRoot . '/assets/', 'forcedFileName' => '%id', 'lowercaseExtension' => true, 'allowedExtensions' => array ('zip'), 'description' => 'Images etc. zipped up without folders, with their filenames exactly matching text in the scenes'),
		);
		$this->template['html'] = $this->editingTable ($this->settings['table'], $dataBindingAttributes, 'graybox lines', false, $sinenomineExtraSettings);
		
		# Process the template
		$html = $this->templatise ();
		
		# Show the HTML
		echo $html;
	}
	
	
	# Callback function to process submitted data
	#!# Currently no support in sinenomine for callbacks when deleting a record, needed in this application to delete the asset files
	public function processUploadedFiles_callback ($record, &$errorHtml = '')
	{
		# Handle each file, e.g. uploaded /scenes/my-scene.zip (with no containing directory in the .zip file) is unzipped to /scenes/my-scene/<files.ext...>
		#!# NB This is done before the sinenomine.php rename on line 1484, so causes a warning there; need to handle this scenario better
		$types = array ('scenes' => 'sceneFile', 'assets' => 'assetsFile');
		foreach ($types as $type => $field) {
			
			# Determine path components
			$directory = $this->applicationRoot . "/{$type}/";
			$newDirectory = $directory . $record['id'] . '/';
			$archivedDirectory = $directory . $record['id'] . '.replacedAt-' . date ('Ymd-His') . '/';
			$filename = $record['id'] . '.zip';
			
			# Archive off any folder from a previous version
			if (is_dir ($newDirectory)) {
				rename ($newDirectory, $archivedDirectory);
			}
			
			# Create the directory
			umask (0);
			mkdir ($newDirectory, 0775);
			
			# Move the zip file into the new directory
			rename ($directory . $filename, $newDirectory . $filename);
			
			# Unzip the file; zip folders should not have a top-level folder
			application::unzip ($filename, $newDirectory, $deleteAfterUnzipping = false);
		}
		
		# Attach the assets and links
		$this->attachAssets ($record['id']);
		
		# Return the record (required by the sinenomine callback specification), unmodified
		return $record;
	}
	
	
	# Function to rewrite asset into the references in the scene data file
	private function attachAssets ($id)
	{
		# Create a backup copy of the original scene data file before rewriting it
		$dataFile = $this->applicationRoot . '/scenes/' . $id . '/app-files/data.js';
		$dataFileOriginalCopy = $dataFile . '.original';
		if (!file_exists ($dataFileOriginalCopy)) {
			if (!copy ($dataFile, $dataFileOriginalCopy)) {
				echo "\n<p class=\"warning\">Error: Unable to back up scene file to <tt>{$dataFileOriginalCopy}</tt>.</p>";
				return false;
			}
		}
		
		# Open the scene file
		$js = file_get_contents ($dataFileOriginalCopy);
		
		# Start replacements
		$replacements = array ();
		
		# Clean out span tags, which sometimes contain fonts, etc.; this is done at the start, to ensure this acts only on the original code, not anything new generated below
		preg_match_all ('@(<span([^>]+)>|</span>)@', $js, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$styleAttribute = $match[1];
			$replacements[$styleAttribute] = '';
		}
		
		# General cleanups
		$replacements['&nbsp;'] = ' ';
		
		# Replace language blocks; see also below
		$replacements['{{es: '] = '<div class="language" data-language="es">';
		$replacements['{{en: '] = '<div class="language" data-language="en">';
		$replacements['}}'] = '</div>';
		
		# Create replacements for URLs to become hyperlinks
		$websiteLinkText = 'Website link / Enlace de página web';
		preg_match_all ("@(https?://.+)(\s|<|\"|&nbsp;)@U", $js, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {	// Loop through each link that has found
			$url = $match[1];	// Backreference 1 is the URL itself
			$replacements[$url] = '<a href="' . $url . '" target="_blank">' . $websiteLinkText . '</a>';
		}
		
		# Standardise the assets file extensions and get the file list
		$assetsDirectory = $this->applicationRoot . '/assets/' . $id . '/';
		$files = directories::standardiseFileExtensions ($assetsDirectory);
		
		# Create a replacement HTML tag for each file, e.g. foo.mp4 is turned into a <video> tag
		foreach ($files as $file) {
			
			# Create HTML tag for each file
			$filename = pathinfo ($file, PATHINFO_BASENAME);
			$path = $this->baseUrl . '/assets/' . $id . '/' . $filename;
			switch (pathinfo ($file, PATHINFO_EXTENSION)) {
				case 'mp4':
					$html = '<video style="width: ' . $this->settings['assetWidth'] . '; display: block;" controls="controls"><source src="' . htmlspecialchars ($path) . '" type="video/mp4" /></video>';
					break;
				case 'm4a':
					$html = '<audio style="width: ' . $this->settings['assetWidth'] . '; display: block;" controls="controls"><source src="' . htmlspecialchars ($path) . '" type="audio/x-m4a" /></audio>';
					break;
				case 'mp3':
					$html = '<audio style="width: ' . $this->settings['assetWidth'] . '; display: block;" controls="controls"><source src="' . htmlspecialchars ($path) . '" type="audio/mpeg" /></audio>';
					break;
				case 'jpg':
				case 'jpeg':
				case 'png':
					$html = '<img style="width: ' . $this->settings['assetWidth'] . '; display: block;" src="' . htmlspecialchars ($path) . '" />';
					break;
				case 'url':
					$contents = file_get_contents ($file);
					preg_match ('/URL=(.+)$/', $contents, $matches);
					$url = $matches[1];
					$html = '<a href="' . htmlspecialchars ($url) . '" target="_blank" title="[Link opens in a new window]">' . $websiteLinkText . '</a>';
					break;
				default:	// Unsupported format
					continue 2;		// Next $file
			}
			
			# Register replacement
			$replacements[$filename] = '<p>' . $html . '</p><br />';
		}
		
		# Convert replacments to escape " as the strings in the file are "-quoted
		foreach ($replacements as $filename => $html) {
			$replacements[$filename] = str_replace ('"', '\\"', $html);
		}
		
		//echo $id; application::dumpData ($replacements);
		
		# Replace filenames with tags, in the data file
		$js = str_replace (array_keys ($replacements), array_values ($replacements), $js);		// Maintains order, unlike strtr
		
		# Add javascript code to end
		$js .= "\n" . $this->customJs ();
		
		# Save the file
		if (!file_put_contents ($dataFile, $js)) {
			echo "\n<p class=\"warning\">Error: Unable to update scene file <tt>{$dataFile}</tt>.</p>";
			return false;
		}
		
		# Confirm success
		return true;
	}
	
	
	# Function to provide static custom JS to be added to the hotspot data file
	public function customJs ()
	{
		return <<<'EOD'
			document.addEventListener ('DOMContentLoaded', function () {
				
				// Add map back link to heading
				const mapLinkHtml = '<p style="position: absolute; left: 10px; top: 3px; font-size: 12px; background-color: gray;"><a href="../../">« Map</a></p>';
				document.querySelector ('#titleBar').insertAdjacentHTML ('afterbegin', mapLinkHtml);
				
				// Define labels for language switcher
				const languagesLabels = {en: 'English', es: 'Español'};
				
				// Find each hotspot
				document.querySelectorAll ('.hotspot .info-hotspot-text').forEach (function (hotspot) {
					
					// Add the language switcher div
					hotspot.innerHTML = hotspot.innerHTML.replace ('<div class="language"', '<div class="language-switcher"></div><div class="language"');    // First only - .replace() is not global replace by default
					
					// Add a link list for each language
					const languagesList = [];
					hotspot.querySelectorAll ('.language').forEach (function (languageDiv) {
						languagesList.push ('<a href="#' + languageDiv.dataset.language + '" style="border: 1px solid #999; padding: 2px 4px; margin-right: 6px;">' + languagesLabels[languageDiv.dataset.language] + '</a>');
					});
					if (languagesList.length) {
						hotspot.querySelectorAll ('.language-switcher')[0].innerHTML = '<p style="margin-bottom: 8px;">' + languagesList.join (' ') + '</p>';
					}
					
					// Function to show the selected language
					function displayLanguage (selectedLanguage) {
						hotspot.querySelectorAll ('.language').forEach (function (languageDiv) {
							languageDiv.style.display = (languageDiv.dataset.language == selectedLanguage ? 'block' : 'none');
						});
						hotspot.querySelectorAll ('.language-switcher a').forEach (function (link) {
							link.style.background = (link.hash.substr (1) == selectedLanguage ? '#777' : 'transparent');
						});
					}
					
					// Show first language initially
					//const initialLanguage = hotspot.querySelectorAll ('.language')[0].dataset.language;
					displayLanguage ('es');
					
					// Switch language when tab clicked on
					hotspot.querySelectorAll ('.language-switcher a').forEach (function (link) {
						link.addEventListener ('click', function () {
							displayLanguage (link.hash.substr (1));
						});
					});
				});
			});
		EOD;
	}
	
	
	# Function to regenerate scene files
	public function regenerate ()
	{
		# Get the list of scenes
		$scenes = $this->databaseConnection->selectPairs ($this->settings['database'], $this->settings['table'], array (), array ('id'));
		
		# Attach assets to each
		foreach ($scenes as $scene) {
			$this->attachAssets ($scene);
		}
		
		# Confirm
		$html  = "\n<p>{$this->tick} Done:</p>";
		$html .= "\n" . application::htmlUl ($scenes);
		
		# Show the HTML
		echo $html;
	}
	
	
	# API call for locations
	public function apiCall_locations ()
	{
		# Set constraints
		$conditions = array ();
		if (!$this->userIsAdministrator) {
			$conditions['live'] = 1;
		}
		
		# Get location data
		$locations = $this->databaseConnection->select ($this->settings['database'], $this->settings['table'], $conditions);
		
		# Omit locations without a scene folder
		foreach ($locations as $id => $location) {
			if (!is_dir ($this->applicationRoot . '/scenes/' . $id . '/')) {
				unset ($locations[$id]);
			}
		}
		
		# Fix up decimal columns
		#!# This needs to be available as an option in FCA passed through to database.php
		foreach ($locations as $id => $location) {
			$locations[$id]['lon'] = (float) $location['lon'];
			$locations[$id]['lat'] = (float) $location['lat'];
		}
		
		# Add in preview image
		foreach ($locations as $id => $location) {
			$previewSearchPath = $this->applicationRoot . '/scenes/' . $id . '/app-files/tiles/0-*/preview.jpg';
			$files = glob ($previewSearchPath);
			$thumbnailFile = $files[0];
			$thumbnailPath = preg_replace ("|^{$this->applicationRoot}|", $this->baseUrl, $thumbnailFile);
			$thumbnailPath = str_replace ('app-files/', '', $thumbnailPath);	// Removed in server rewrite
			$locations[$id]['thumbnail'] = $thumbnailPath;
		}
		
		# Convert to GeoJSON
		$geojson = array ('type' => 'FeatureCollection', 'features' => array ());
		foreach ($locations as $location) {
			$geojson['features'][] = array (
				'type' => 'Feature',
				'geometry' => array (
					'type' => 'Point',
					'coordinates' => array ($location['lon'], $location['lat']),
				),
				'properties' => application::arrayFields ($location, array ('id', 'title', 'thumbnail', 'description')),
			);
		}
		
		# Return the data
		return $geojson;
	}
}

?>
