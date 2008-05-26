<?php

// php page: task_parameter.php

// This file is part of huygens remote manager.

// Copyright: Montpellier RIO Imaging (CNRS)

// contributors :
// 	     Pierre Travo	(concept)
// 	     Volker Baecker	(concept, implementation)

// email:
// 	pierre.travo@crbm.cnrs.fr
// 	volker.baecker@crbm.cnrs.fr

// Web:     www.mri.cnrs.fr

// huygens remote manager is a software that has been developed at 
// Montpellier Rio Imaging (mri) in 2004 by Pierre Travo and Volker 
// Baecker. It allows running image restoration jobs that are processed 
// by 'Huygens professional' from SVI. Users can create and manage parameter 
// settings, apply them to multiple images and start image processing 
// jobs from a web interface. A queue manager component is responsible for 
// the creation and the distribution of the jobs and for informing the user 
// when jobs finished.

// This software is governed by the CeCILL license under French law and 
// abiding by the rules of distribution of free software. You can use, 
// modify and/ or redistribute the software under the terms of the CeCILL 
// license as circulated by CEA, CNRS and INRIA at the following URL 
// "http://www.cecill.info".

// As a counterpart to the access to the source code and  rights to copy, 
// modify and redistribute granted by the license, users are provided only 
// with a limited warranty and the software's author, the holder of the 
// economic rights, and the successive licensors  have only limited 
// liability.

// In this respect, the user's attention is drawn to the risks associated 
// with loading, using, modifying and/or developing or reproducing the 
// software by the user in light of its specific status of free software, 
// that may mean that it is complicated to manipulate, and that also 
// therefore means that it is reserved for developers and experienced 
// professionals having in-depth IT knowledge. Users are therefore encouraged 
// to load and test the software's suitability as regards their requirements 
// in conditions enabling the security of their systems and/or data to be 
// ensured and, more generally, to use and operate it in the same conditions 
// as regards security.

// The fact that you are presently reading this means that you have had 
// knowledge of the CeCILL license and that you accept its terms.

require_once("./inc/User.inc");
require_once("./inc/Parameter.inc");
require_once("./inc/Setting.inc");

session_start();

if (isset($_GET['exited'])) {
  $_SESSION['user']->logout();
  session_unset();
  session_destroy();
  header("Location: " . "login.php"); exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

if (!isset($_SESSION['task_setting'])) {
  session_register("task_setting"); 
  $_SESSION['task_setting'] = new TaskSetting();
}
if ($_SESSION['user']->name() == "admin") $_SESSION['task_setting']->setNumberOfChannels(5);
else $_SESSION['task_setting']->setNumberOfChannels($_SESSION['setting']->numberOfChannels());

$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

$parameter = $_SESSION['task_setting']->parameter("FullRestoration");

// TODO refactor code to consider only full restorations
if ($parameter->value() == "False") {
  
  $parameter->setValue("True");
  $_SESSION['task_setting']->set($parameter);
  
  $parameter = $_SESSION['task_setting']->parameter("RemoveBackground");
  $parameter->setValue("False");
  $_SESSION['task_setting']->set($parameter);
  
}
else {

  // TODO refactor code to never consider remove noise
  $parameter = $_SESSION['task_setting']->parameter("RemoveNoise");
  
  if (isset($_POST['RemoveNoise'])) {
    $parameter->setValue("True");
  }
  else {
    $parameter->setValue("False");
  }
  $_SESSION['task_setting']->set($parameter);
  
  $names = $_SESSION['task_setting']->parameterNames();
  foreach ($names as $name) {
    $parameter = $_SESSION['task_setting']->parameter($name);
    if (isset($_POST[$name])) {
      $parameter->setValue($_POST[$name]);
      $_SESSION['task_setting']->set($parameter);
    }
    /*else {
      $value = $parameter->value();
      if ($parameter->isBoolean() && isset($_POST['OK'])) {
        $parameter->setValue("False");
        $_SESSION['task_setting']->set($parameter);
      }
    }*/
  }
  
  // number of iterations: set the use of range to false if checkbox is unchecked
  $parameter = $_SESSION["task_setting"]->parameter("NumberOfIterationsUseRange");
  if (isset($_POST["OK"]) && !isset($_POST["NumberOfIterationsUseRange"])) {
        $parameter = $_SESSION["task_setting"]->parameter("NumberOfIterationsUseRange");
        $parameter->setValue("False");
        $_SESSION["task_setting"]->set($parameter);
  }
  
  $signalNoiseRatioParam =  $_SESSION['task_setting']->parameter("SignalNoiseRatio");
  $signalNoiseRatio = $signalNoiseRatioParam->internalValue();
  $backgroundOffsetPercentParam =  $_SESSION['task_setting']->parameter("BackgroundOffsetPercent");
  $backgroundOffset = $backgroundOffsetPercentParam->internalValue();
  for ($i=1; $i <= $_SESSION['task_setting']->numberOfChannels(); $i++) {
    $signalNoiseRatioKey = "SignalNoiseRatio{$i}";
    $backgroundOffsetKey = "BackgroundOffsetPercent{$i}";
    if (isset($_POST[$signalNoiseRatioKey])) {
      $signalNoiseRatio[$i] = $_POST[$signalNoiseRatioKey];
    } 
    if (isset($_POST[$backgroundOffsetKey])) {
      $backgroundOffset[$i] = $_POST[$backgroundOffsetKey];
    } 
  }
  // get rid of extra values in case the number of channels is changed
  /*$signalNoiseRatio = array_slice($signalNoiseRatio, 0, $_SESSION['setting']->numberOfChannels() + 1);
  $backgroundOffset = array_slice($backgroundOffset, 0, $_SESSION['setting']->numberOfChannels() + 1);*/
  $signalNoiseRatioParam->setValue($signalNoiseRatio);
  $_SESSION['task_setting']->set($signalNoiseRatioParam);
  $backgroundOffsetPercentParam->setValue($backgroundOffset);
  $_SESSION['task_setting']->set($backgroundOffsetPercentParam);
  
  if (isset($_POST['BackgroundEstimationMode']) && $_POST['BackgroundEstimationMode'] == "auto") {
    $parameter = $_SESSION['task_setting']->parameter("BackgroundOffsetPercent");
    $parameter->setValue(array(1 => "auto"));
    $_SESSION['task_setting']->set($parameter);
  }
  else if (isset($_POST['BackgroundEstimationMode']) && $_POST['BackgroundEstimationMode'] == "object") {
    $parameter = $_SESSION['task_setting']->parameter("BackgroundOffsetPercent");
    $parameter->setValue(array(1 => "object"));
    $_SESSION['task_setting']->set($parameter);
  }
  
  $signalNoiseRatioRangeParam = $_SESSION['task_setting']->parameter("SignalNoiseRatioRange");
  $backgroundOffsetRangeParam = $_SESSION['task_setting']->parameter("BackgroundOffsetRange");
  $numberOfIterationsRangeParam = $_SESSION['task_setting']->parameter("NumberOfIterationsRange");
  $signalNoiseRatioRange = $signalNoiseRatioRangeParam->value();
  $backgroundOffsetRange = $backgroundOffsetRangeParam->value();
  $numberOfIterationsRange = $numberOfIterationsRangeParam->value();
  for ($i=1; $i <= 4; $i++) {
    $signalNoiseRatioRangeKey = "SignalNoiseRatioRange{$i}";
    if (isset($_POST[$signalNoiseRatioRangeKey])) {
      $signalNoiseRatioRange[$i] = $_POST[$signalNoiseRatioRangeKey];
    }
    $backgroundOffsetRangeKey = "BackgroundOffsetRange{$i}";
    if (isset($_POST[$backgroundOffsetRangeKey])) {
      $backgroundOffsetRange[$i] = $_POST[$backgroundOffsetRangeKey];
    } 
    $numberOfIterationsRangeKey = "NumberOfIterationsRange{$i}";
    if (isset($_POST[$numberOfIterationsRangeKey])) {
      $numberOfIterationsRange[$i] = $_POST[$numberOfIterationsRangeKey];
    } 
  }
  $signalNoiseRatioRangeParam->setValue($signalNoiseRatioRange);
  $backgroundOffsetRangeParam->setValue($backgroundOffsetRange);
  $numberOfIterationsRangeParam->setValue($numberOfIterationsRange);
  $_SESSION['task_setting']->set($signalNoiseRatioRangeParam);
  $_SESSION['task_setting']->set($backgroundOffsetRangeParam);
  $_SESSION['task_setting']->set($numberOfIterationsRangeParam);
  
  if (isset($_POST['QualityChangeStoppingCriterion'])) {
    $parameter = $_SESSION['task_setting']->parameter("QualityChangeStoppingCriterion");
    $parameter->setValue($_POST['QualityChangeStoppingCriterion']);
    $_SESSION['task_setting']->set($parameter);
  }
  
  if (count($_POST) > 0) {
    $ok = $_SESSION['task_setting']->checkParameter();
    $message = "            <p class=\"warning\">".$_SESSION['task_setting']->message()."</p>\n";
    if ($ok) {
      $saved = $_SESSION['task_setting']->save();			
      $message = "            <p class=\"warning\">".$_SESSION['task_setting']->message()."</p>\n";
      if ($saved) {
        header("Location: " . "select_task_settings.php"); exit();
      }
    }	 
  }

}

$noRange = False;

$script = "settings.js";

include("header.inc.php");

?>

    <div id="nav">
        <ul>
            <li><a href="select_images.php?exited=exited">exit</a></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpRestorationParameters')">help</a></li>
        </ul>
    </div>
    
    <div id="content">
    
        <h3>Task Setting</h3>
        
        <form method="post" action="" id="select">
        
            <fieldset class="setting">  <!-- signal/noise ratio -->
            
                <legend>
                    <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=SignalToNoiseRatio')"><img src="images/help.png" alt="?" /></a>
                    signal/noise ratio
                </legend>
                
                <div id="snr">
                
                    <div class="multichannel">
<?php

$parameter = $_SESSION['task_setting']->parameter("SignalNoiseRatio");
$value = $parameter->value();
for ($i=1; $i <= $_SESSION['task_setting']->numberOfChannels(); $i++) {

?>
                        <span class="nowrap">Ch<?php echo $i ?>:<span class="multichannel"><input name="SignalNoiseRatio<?php echo $i ?>" type="text" size="8" value="<?php echo $value[$i] ?>" class="multichannelinput" /></span>&nbsp;</span>
<?php

}

?>
                    </div>
                    
                </div>
                
            </fieldset>
            
            <fieldset class="setting">  <!-- background mode -->
            
                <legend>
                    <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=BackgroundMode')"><img src="images/help.png" alt="?" /></a>
                    background mode
                </legend>
                
                <div id="background">
                
<?php

$backgroundOffsetPercentParam =  $_SESSION['task_setting']->parameter("BackgroundOffsetPercent");
$backgroundOffset = $backgroundOffsetPercentParam->internalValue();

$flag = "";
if ($backgroundOffset[1] == "" || $backgroundOffset[1] == "auto") $flag = " checked=\"checked\"";

?>

                    <input type="radio" name="BackgroundEstimationMode" value="auto"<?php echo $flag ?> />automatic background estimation<p />
                    
<?php

$flag = "";
if ($backgroundOffset[1] == "object") $flag = " checked=\"checked\"";

?>

                    <input type="radio" name="BackgroundEstimationMode" value="object"<?php echo $flag ?> />in/near object<p />
                    
<?php

$flag = "";
if ($backgroundOffset[1] != "" && $backgroundOffset[1] != "auto" && $backgroundOffset[1] != "object") $flag = " checked=\"checked\"";

?>
                    <input type="radio" name="BackgroundEstimationMode" value="manual"<?php echo $flag ?> />
                    remove constant absolute value
                    
                    <div class="multichannel">
<?php

for ($i=1; $i <= $_SESSION['task_setting']->numberOfChannels(); $i++) {
  $val = "";
  if ($backgroundOffset[1] != "auto" && $backgroundOffset[1] != "object" && $i < sizeof($backgroundOffset)) $val = $backgroundOffset[$i];

?>
                        <span class="nowrap">Ch<?php echo $i ?>:<span class="multichannel"><input name="BackgroundOffsetPercent<?php echo $i ?>" type="text" size="8" value="<?php echo $val ?>" class="multichannelinput" /></span>&nbsp;</span>
                        
<?php

}

?>
                    </div>
                    
                </div>
                
            </fieldset>
            
            <fieldset class="setting">  <!-- stopping criteria -->
            
                <legend>
                    stopping criteria
                </legend>
                
                <div id="criteria">
                
                    <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=MaxNumOfIterations')"><img src="images/help.png" alt="?" /></a>
                    number of iterations:
                    
<?php

$parameter = $_SESSION['task_setting']->parameter("NumberOfIterations");
$value = 40;
if ($parameter->value() != null) {
  $value = $parameter->value();
}

?>
                    <input name="NumberOfIterations" type="text" size="3" value="<?php echo $value ?>" />
                    
                    <p />
                    
<?php

if (!$noRange) {

?>
                    <div style="text-align: left">
                    
<?php

$parameter = $_SESSION['task_setting']->parameter("NumberOfIterationsUseRange");

?>
                        <?php echo $parameter->printCheckBox(""); ?>
                        
                        try multiple values
                    
<?php

$numberOfIterationsRangeParam = $_SESSION['task_setting']->parameter("NumberOfIterationsRange");
$numberOfIterationsRange = $numberOfIterationsRangeParam->value();


  for ($i=1; $i <= 4; $i++) {

?>
                        <input name="NumberOfIterationsRange<?php echo $i ?>" type="text" size="3" value="<?php echo $numberOfIterationsRange[$i] ?>" class="multichannelinput" />
                        
<?php

  }

?>
                    </div>
<?php

}

?>

                    <p />
                    
                    <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=QualityCriterion')"><img src="images/help.png" alt="?" /></a>
                    quality change:
                    
<?php

$parameter = $_SESSION['task_setting']->parameter("QualityChangeStoppingCriterion");
$value = 0.1;
if ($parameter->value() != null) {
  $value = $parameter->value();
}

?>
                    <input name="QualityChangeStoppingCriterion" type="text" size="3" value="<?php echo $value ?>" />
                    
                </div>
                
            </fieldset>
            
            <div><input name="OK" type="hidden" /></div>
            
        </form>
        
    </div> <!-- content -->
    
    <div id="stuff">
    
        <div id="info">
        
            <input type="button" value="" class="icon cancel" onclick="document.location.href='select_task_settings.php'" />
            <input type="submit" value="" class="icon apply" onclick="process()" />
            
            <p>
                Define the parameters for restoration.
            </p>

	   <p>
		You will find detailed explanations by following the help button in the navigation bar.
	   </p>

           <p>
                When you are ready, press the <br />
                <img src="images/apply_help.png" alt="Apply" width="22" height="22" /> <b>apply</b>
                button to go to the next <br />step
                or <img src="images/cancel_help.png" alt="Cancel" width="22" height="22" /> <b>cancel</b>
                to discard your changes.
            </p> 
    
        </div>
        
        <div id="message">
<?php

echo $message;

?>
        </div>
        
    </div> <!-- stuff -->
    
<?php

include("footer.inc.php");

?>
