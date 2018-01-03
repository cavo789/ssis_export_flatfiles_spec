<?php
/**
 * Author : AVONTURE Christophe
 * Date	: January 2018
 *
 * Description :
 * With Visual Studio 2015, it's still impossible to export the
 * description of a file i.e. in the Flat file connection
 * manager, we can describe the layout of a file, define the list
 * of columns, their type, size, ... so the import can be done.
 *
 * Problem : we can't export that specification to put it in our
 * technical documentation and, of course, it's stupid to retype it.
 *
 * The idea :
 *
 *	- Retrieve the XML description of the SSIS package,
 *	- From there, retrieve the list of fields
 *	- Make a few changes (like replacing the `DTS:DataType` in an
 *		human readable format
 *	- And export the list as a `.csv` file
 *
 * How to use this script :
 *
 * 	1. On the disk, retrieve where you've saved your Visual Studio
 * 		Solution (this is a `.sln` file) (f.i. `mySolution.sln`).
 *	2. You'll find there a folder with the same name
 *		(f.i. folder `mySolution`)
 *	3. Open that folder and continue by opening the
 *		`bin\Development` folder
 *	4. You'll find a `.ispac` file
 *	5. Right-clic on it and unzip it (yes, the `.ispac` file is,
 *		in fact, a `.zip` archive
 *	6. Go into the newly created folder
 *	7. Get a copy of the `Package.dtsx` file and put it into a
 *		local website folder (f.i. `c:\sites\dtsx\`)
 *	8. Put a copy of this PHP script in that folder and run it
 *		(preferred way is to run it from a localhost website but
 *		with a few changes it should be possible to run it from the
 *		command line (CLI)
 *
 * If everything goes fine, you'll have as much `.csv` file that
 * you've flatfiles connections manager in your `.dtsx`.
 *
 * The name of these files will be the name given to the connection
 * manager and filenames will be displayed on the screen.
 */

define ('DS', DIRECTORY_SEPARATOR);

/**
 * Replace internal datatype code by their description.
 * @link https://msdn.microsoft.com/en-us/library/microsoft.sqlserver.dts.runtime.wrapper.datatype.aspx
 */
function getHumanType($sDataType)
{
	// This array is NOT EXHAUSTIVE; if you need more, please
	// see the link give here above and add yours.
	$arr = array(
		"4" => "float [DT_R4]",
		"19" => "four-byte unsigned integer [DT_UI4]",
		"129" => "string [DT_STR]",
		"130" => "Unicode string [DT_WSTR]"
	);

	// Because we need a default value
	$sReturn = 'undefined';

	foreach ($arr as $key=>$value) {
		if ($sDataType == $key) {
			$sReturn = $value;
			break;
		}
	}

	return $sReturn;
}

/**
 * Process every columns defined in the sXML string (a string, not
 * an object). $name is the name given to the connection manager.
 *
 * @output : the list of columns as a CSV content.
 *
 * Example : (fake)
 *
 *	#;Start;End;FieldName;FieldType;FieldSize
 *	2;1;13;Title;Unicode string [DT_WSTR];13
 *	3;14;19;Gender;Unicode string [DT_WSTR];6
 *	4;20;20;FirstName;Unicode string [DT_WSTR];1
 *	5;21;22;Surname;Unicode string [DT_WSTR];2
 *	6;23;31;Age;float [DT_R4];9
 */
function makeCSV($name, $sXML)
{
	echo '<h3>Export specification for flatfile ['.$name.']</h3>';

	// Add the root element in order to defined the xmlns prefix
	// and avoid warnings fired by simplexml_load_string due to
	// the absence of the prefix used by SSIS.
	$sXML = '<?xml version="1.0" standalone="yes"?>'.
		'<root xmlns:DTS="www.microsoft.com/SqlServer/Dts">'.
		$sXML.
		'</root>';

	// Load the string and make a XML object
	$xml = simplexml_load_string($sXML);

	// Get the list of all columns
	$result = $xml->xpath('//DTS:FlatFileColumn');

	// Process columns one by one
	$sCSV = '#;Start;End;FieldName;FieldType;FieldSize'.PHP_EOL;
	$wStart = 1;
	$i = 0;

	while (list( , $node) = each($result)) {
		$i++;

		// Retrieve field's name, type and size
		$sName=$node->xpath('@DTS:ObjectName')[0];
		$sType=getHumanType(trim($node->xpath('@DTS:DataType')[0]));
		$wSize=intval($node->xpath('@DTS:ColumnWidth')[0]);

		// Prepare the output
		$wEnd = $wStart + $wSize - 1;

		$sCSV .= ($i+1).';'.$wStart.';'.$wEnd.';'.
			$sName.';'.$sType.';'.$wSize.PHP_EOL;

		$wStart = $wEnd + 1;
	} // while() {

	return $sCSV;
}

// ---------------------
// Entry point
// ---------------------
 	echo '<!DOCTYPE html>'.
		'<html lang="en">'.
		'<head>'.
		'<meta charset="utf-8"/>'.
		'<meta name="viewport" content="width=device-width, initial-scale=1">'.
		'<style>body{font-size:1em;margin:10px auto;padding-top:10px;width:80%;}pre{background-color:LightGray;padding:10px;width:75%;white-space:pre-line;line-height:1.5em;border-radius:10px;}.failure{color:red;}.success{color:green;}</style>'.
		'</head><body><h1>Export Flatfiles connection managers '.
		'to .csv files</h1>';

	// Process every .dtsx file present in the folder
	$arrFiles = glob('*.dtsx');

	// If no .dtsx file, nothing to do
	if (count($arrFiles) == 0) {
		echo '<p>There is no .dtsx file in the '.__DIR__.' folder.</p>';
		echo '<p>Nothing to do.</p>';
		echo '<p>Have a nice day.</p>';
		die();
	}

	// Process each file
	foreach ($arrFiles as $sFile) {
		echo '<h2>Process '.$sFile.'</h2>';

		// Load the XML file
		$xml = simplexml_load_file($sFile);

		// We'll process only the list of connection managers
		$result = $xml->xpath('//DTS:ConnectionManagers/DTS:ConnectionManager');

		// In a .dtsx file, we can have more than one connection
		// manager, process each of them
		while (list( , $node) = each($result)) {
			// Get the creation name attribute. Should be "flatfile"
			$attribute=$node->xpath('@DTS:CreationName')[0];

			if (strtolower($attribute) == 'flatfile') {
				// It's the declaration of a flatfile

				// Get the name given to the connection manager
				// (probably the name of the file)
				$name=$node->xpath('@DTS:ObjectName')[0];

				$tmp = $node->asXML();
				$sCSV = makeCSV($name, $tmp);

				echo '<h4 class="success">Create '.__DIR__.DS.$name.'.csv</h4>';
				echo '<pre>'.$sCSV.'</pre>';

				// Create the file on disk
				file_put_contents($name.'.csv', $sCSV);
			} // if (flatfile)
		} // while ()
	} // foreach ($arrFiles)

	echo '<hr/><footer>Process finished</footer></body></html>';