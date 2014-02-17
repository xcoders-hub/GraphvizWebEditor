<?php

// @FIXME: I think the functinality is clear. Time to clean this code into seperate classes.

    set_error_handler(
        function ($errno, $errstr, $errfile, $errline ) {
            throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
        }
    );

    $sOutput = '';
    $bError = false;
    $bVerbose = false;
    $bShowPrevious = true;
    $sGraph = '';
    $aSupportedImageTypes = array('png', 'svg');

    // @FIXME: Sanitize user input!
    if(isset($_POST['graph'])) {
        $sGraph = $_POST['graph'];
    }

    if(isset($_POST['verbose'])) {
        $bVerbose = true;
    }

    if(isset($_POST['token'])) {
        $sPreviousToken = $_POST['token'];
    }

    if(isset($_POST['show-previous']) === false) {
        $bShowPrevious = false;
    }

    if(isset($_POST['image-type']) && in_array($_POST['image-type'], $aSupportedImageTypes)) {
        $sImageType = $_POST['image-type'];
    } else {
        $sImageType = 'png';
    }

    $sExtension = '.' . $sImageType;

    $sFileStorePath = PROJECT_ROOT . '/web/file/';

    $bRedirect = false;
    if(isset($_GET['token']) && file_exists($sFileStorePath . $_GET['token'] . '.dot') === true){
        if($sGraph === ''){
            $sToken = $_GET['token'];
            $sFile = $sFileStorePath . $sToken . '.dot';
            $sGraph = file_get_contents($sFile);
        } else {
            // Create new graph and redirect to it
            $sToken = md5($sGraph);
            $sFile = $sFileStorePath . $sToken . '.dot';
            $bRedirect = true;
        }
    } else {
        if($sGraph === ''){
            $sGraph = file_get_contents(PROJECT_ROOT . '/example.dot');
        }
        $sToken = md5($sGraph);
        $sFile = $sFileStorePath . $sToken . '.dot';
    }

    $sGraphHtml = '<a href="./file/' . $sToken . '.dot' . $sExtension . '" target="_blank"><img src="./file/' . $sToken . '.dot' . $sExtension . '" /></a>';

    if(file_exists($sFile . $sExtension) === true) {
        $sOutput = 'File already exists';
    } else {
        if(file_exists($sFile) === false) {
            try {
                file_put_contents($sFile, $sGraph);
            } catch(\Exception $eAny){
                $bError = true;
                $sOutput = $eAny->getMessage();
            }
        }

        if($bError === false) {
            $aResult = array();
            $sFlags =
                  ($bVerbose?' -v':'')
                . ' -T' . $sImageType .' '              // Output Type
                . ' -o "' . $sFile . $sExtension . '"'  // Output File
                . ' "' . $sFile . '"'                   // Input File
            ;

            try {
                $aResult = executeCommand('dot ' . $sFlags, $sGraph);
                $sOutput .= $aResult['stdout'];
                $sOutput .= $aResult['stderr'];
            } catch(\Exception $eAny){
                $bError = true;
                $sOutput = $eAny->getMessage();
                $aResult['return'] = 256;
            }

            if($aResult['return'] > 0 && file_exists($sFile . $sExtension) === false){
                $bError = true;
            }
        }

        if($bError === true){
            $sToken = 'Error!';
            $sGraphHtml = '';
        }
    }
    if ($bRedirect === true) {
        $sUrl = $_SERVER['REQUEST_URI'];
        $iQueryPosition = strpos($sUrl, '?');
        if ($iQueryPosition === false) {
            $sUrl .= '?token=' . $sToken;
        } else {
            $sUrl = substr_replace($sUrl, '?token=' . $sToken, $iQueryPosition);
        }

        header("Location: " . $sUrl);
        die;
    } else {
        $sOutput = str_replace(__DIR__, '', $sOutput);
    }


/*
 * Because exec/sytem/etc. Are a bit lame in giving error feedback a workaround
 * is required. Instead of executing commands derictly, we open a stream, write
 * the command to the stream and read whatever comes back out of the pipes.
 *
 * For general info on Standard input (stdin), Standard output (stdout) and
 * Standard error (stderr) please visit:
 *      http://en.wikipedia.org/wiki/Standard_streams
 */
function executeCommand($p_sCommand, $p_sInput='') {

    $proc = proc_open(
        $p_sCommand
        , array(
              0 => array('pipe', 'r')
            , 1 => array('pipe', 'w')
            , 2 => array('pipe', 'w'))
        , $aPipes
    );

    fwrite($aPipes[0], $p_sInput);
    fclose($aPipes[0]);


    $sStandardOutput = stream_get_contents($aPipes[1]);
    fclose($aPipes[1]);

    $sStandardError = stream_get_contents($aPipes[2]);
    fclose($aPipes[2]);

    $iReturn=proc_close($proc);

    return array(
          'stdin'  => $p_sCommand
        , 'stdout' => $sStandardOutput
        , 'stderr' => $sStandardError
        , 'return' => $iReturn
    );
}

#EOF