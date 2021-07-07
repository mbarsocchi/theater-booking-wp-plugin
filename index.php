<?php

/*
 Plugin Name: Theater booking
Plugin URI: http://www.mirobarsa.com
Description: Manage booking for a theater
Version: 0.1
Author: Miro Barsocchi
Author URI: http://www.mirobarsa.com
*/
define('THEATER_BOOKING_TBNAME', 'mb_prenotazioni');
define('SPETTACOLI_TBNAME', 'mb_spettacoli');
define('TMP_FOLDER_FOR_URL','wp-content/plugins/theater_preno/tmp/');
define('TMP',ABSPATH .TMP_FOLDER_FOR_URL );
define('BASE_IMAGE_FOLDER', ABSPATH . "wp-content/plugins/theater_preno/img/");
define('BASE_IMAGE_FOLDER_URL', "/wp-content/plugins/theater_preno/img/");
define('UNIQUE_TICKET_COST',11);
define('STOP_PRENO_HOUR', 19000);

require_once(ABSPATH . 'wp-content/plugins/theater_preno/html2pdf_v4.03/html2pdf.class.php');
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');


function theater_mb_activate() {

	global $wpdb;

	$preno_table = $wpdb->prefix . THEATER_BOOKING_TBNAME;
	$spettacoli_table = $wpdb->prefix . SPETTACOLI_TBNAME;

	$query = "CREATE TABLE IF NOT EXISTS `" . $preno_table . "` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_spettacolo` int(11) NOT NULL,
  `nome` text NOT NULL,
  `riferimento` text NOT NULL,
  `_ins` datetime NOT NULL,
  `_upd` datetime DEFAULT NULL,
  `prenocode` text NOT NULL,
  PRIMARY KEY (`id_spettacolo`),
   UNIQUE KEY `id` (`id`),
  KEY `". $spettacoli_table."` (`id_spettacolo`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;";

	dbDelta($query);

	$query = "CREATE TABLE IF NOT EXISTS `" . $spettacoli_table . "` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` text NOT NULL,
  `luogo` text NOT NULL,
  `dettagli` text NOT NULL,
  `data_inizio` datetime NOT NULL,
  `data_fine` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";


	dbDelta($query);

	$query = "ALTER TABLE `" . $preno_table . "`
  ADD CONSTRAINT `wp_mb_prenotazioni_ibfk_1` FOREIGN KEY (`id_spettacolo`) REFERENCES `" . $spettacoli_table . "` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;";

	dbDelta($query);
}

function insertInDb($name, $user, $day) {
	global $wpdb;
	$spettacoli_table = $wpdb->prefix . SPETTACOLI_TBNAME;
	$preno_table = $wpdb->prefix . THEATER_BOOKING_TBNAME;
	$sql = "SELECT id,posti FROM $spettacoli_table WHERE data = '" . $day . "'";
	$result = $wpdb->get_results($sql, ARRAY_A);
	$id = $result[0]['id'];
	$today = date("y-m-d G:i:s");
	$sql = "SELECT count(1) FROM $preno_table WHERE id_spettacolo = " . $id . "";
	$count = $wpdb->get_var($sql);
	if ($count + 1 > $result[0]['posti']) {
		echo "non ci sono pi&igrave; posti";
	} else {
		$wpdb->insert($preno_table, array('id_spettacolo' => $id, 'nome' => $name,
            'riferimento' => $user,
            '_ins' => $today));
	}
}

function updateShow($id,$date,$nome,$location,$details,$seats){
	global $wpdb;
	$shows_table = $wpdb->prefix . SPETTACOLI_TBNAME;
	$convertedDate= date("y-m-d G:i:s", strtotime($date));
	$returned = $wpdb->update($shows_table, 
	array('nome' =>$nome,
	      'luogo' =>$location,
	      'dettagli' =>$details,
	      'data'  =>$convertedDate,
	      'posti' =>$seats),
	array('id' => $id));
	return $returned;
}

function deleteShow($id) {
	global $wpdb;
	$userThatCanModify = $current_user->user_login;
	$show_table = $wpdb->prefix . SPETTACOLI_TBNAME;
	$query = "DELETE FROM $show_table WHERE id = $id";
	return $wpdb->query($query);
}

function insertShow($timestamp,$name,$location,$details,$seats){
	global $wpdb;
	$spettacoli_table = $wpdb->prefix . SPETTACOLI_TBNAME;
	$convertedDate= date("y-m-d G:i:s", strtotime($timestamp));
	$wpdb->insert($spettacoli_table, array(
				'nome' => $name,
	            'luogo' => $location,
	            'dettagli' => $details,
				'data' => $convertedDate,
				'posti' => $seats));
}

function returnPrenotDataTableForArrysOfId($idArrays) {
	global $wpdb;
	$preno_table = $wpdb->prefix . THEATER_BOOKING_TBNAME;
	$spettacoli_table = $wpdb->prefix . SPETTACOLI_TBNAME;
	$stringOfIds.=implode(",", $idArrays);

	$sql = "SELECT t1.nome, t2.data,t1.id,t1.riferimento FROM $preno_table AS t1 JOIN $spettacoli_table AS t2 WHERE t1.id_spettacolo = t2.id AND t1.id in(".$stringOfIds.") ORDER BY t1.nome";
	$preno = $wpdb->get_results($sql, ARRAY_A);
	return $preno;
}

function returnPrenotDataTable($incondition=null) {
	global $wpdb;
	$preno_table = $wpdb->prefix . THEATER_BOOKING_TBNAME;
	$spettacoli_table = $wpdb->prefix . SPETTACOLI_TBNAME;
	$now = date("y-m-d G:i:s");
	if($incondition == null){
		$sql = "SELECT t1.nome, t2.data,t1.id,t1.riferimento,t1.prenocode FROM $preno_table AS t1 JOIN $spettacoli_table AS t2 WHERE t1.id_spettacolo = t2.id AND t2.data > '$now' ORDER BY t1.nome";
	}else{
		$sql = "SELECT t1.nome, t2.data,t1.id,t1.riferimento,t1.prenocode FROM $preno_table AS t1 JOIN $spettacoli_table AS t2 WHERE t1.id_spettacolo = t2.id AND t1.id_spettacolo IN $incondition AND t2.data > '$now' ORDER BY t1.nome";
	}
	$preno = $wpdb->get_results($sql, ARRAY_A);
	return $preno;
}

function returnPrenotDataTableForId($idSpettacolo) {
	global $wpdb;
	$preno_table = $wpdb->prefix . THEATER_BOOKING_TBNAME;
	$spettacoli_table = $wpdb->prefix . SPETTACOLI_TBNAME;
	$sql = "SELECT t2.data,t2.posti, t1.nome, t1.riferimento, t1._ins, t1._upd,t1.prenocode 
FROM $preno_table AS t1, $spettacoli_table AS t2
WHERE t1.id_spettacolo =$idSpettacolo AND t2.id = $idSpettacolo ORDER BY t1.nome";
	$preno = $wpdb->get_results($sql, ARRAY_A);
	return $preno;
}

function returnDataForSpettacoloId($idSpettacolo){
	global $wpdb;
	$spettacoli_table = $wpdb->prefix . SPETTACOLI_TBNAME;
	$sql = "SELECT * FROM $spettacoli_table WHERE id = $idSpettacolo";
	$spettacoloInfo = $wpdb->get_results($sql, ARRAY_A);
	return $spettacoloInfo;
}

function retriveAllfutureShow(){
	global $wpdb;
	$spettacoli_table = $wpdb->prefix . SPETTACOLI_TBNAME;
	$now = date("y-m-d G:i:s",time());
	$sql = "SELECT * FROM $spettacoli_table WHERE data > '$now' ORDER BY data ASC";
	$spettacoloFuture = $wpdb->get_results($sql, ARRAY_A);
	return $spettacoloFuture;
}

function updatePrenoName($namePost, $idPost) {
	if (isset($namePost) && isset($idPost)) {
		global $wpdb;
		$preno_table = $wpdb->prefix . THEATER_BOOKING_TBNAME;
		$current_user = wp_get_current_user();
		$currentUserName = $current_user->user_login;
		$today = date("y-m-d G:i:s");
		if (!current_user_can('manage_options')) {
			$returned = $wpdb->update($preno_table, array('nome' => $_POST['firstname'], '_upd' => $today),
			array('id' => $_POST['id'],
                                'riferimento' => $currentUserName));
		} else {
			$returned = $wpdb->update($preno_table, array('nome' => $_POST['firstname'], '_upd' => $today), array('id' => $_POST['id']));
		}
	}
	return $returned;
}

function updatePrenoWithGeneratedCode($id,$riferimentoSent,$code) {
	global $wpdb;
	$preno_table = $wpdb->prefix . THEATER_BOOKING_TBNAME;
	$current_user = wp_get_current_user();
	$currentUserName = $current_user->user_login;
	$today = date("y-m-d G:i:s");
	if (!current_user_can('manage_options') && $riferimentoSent ==$currentUserName ) {
		$returned = $wpdb->update($preno_table, array('_upd' => $today, 'prenocode'=>$code),
		array('id' => $id,
              'riferimento' => $currentUserName));
	} else {
		$returned = $wpdb->update($preno_table, array('_upd' => $today, 'prenocode'=>$code), array('id' => $id));
	}
	return $returned;
}

function deletePreno($id) {
	global $wpdb;
	$current_user = wp_get_current_user();
	$userThatCanModify = $current_user->user_login;
	$preno_table = $wpdb->prefix . THEATER_BOOKING_TBNAME;
	if (!current_user_can('manage_options')) {
		$query = "DELETE FROM $preno_table WHERE id = $id AND riferimento='" . $userThatCanModify . "'";
	} else {
		$query = "DELETE FROM $preno_table WHERE id = $id";
	}
	return $wpdb->query($query);
}

function returnDateOfShows($limit=false) {
	global $wpdb;
	$spettacoli_table = $wpdb->prefix . SPETTACOLI_TBNAME;
	$now = time();
	if ($limit) {
		$today = date("y-m-d G:i:s",$now+STOP_PRENO_HOUR);
	}else{
		$today = date("y-m-d G:i:s",$now);
	}
	$sql = "SELECT data,posti,id FROM $spettacoli_table WHERE data > '$today' ORDER BY `$spettacoli_table`.`data` ASC";
	$preno = $wpdb->get_results($sql, ARRAY_A);
	return $preno;
}

function wp_teather_mb_writeOnTicket($name,$ref,$code,$showDate){
	//header( 'Content-type: image/jpeg' );
	$im    = imagecreatefromjpeg(BASE_IMAGE_FOLDER."ticket.jpg");
	$black = imagecolorallocate($im, 0, 0, 0);
	imagestring($im, 5,160, 18, $name, $black);
	imagestring($im, 5, 184, 55, $ref, $black);
	imagestring($im, 5, 168, 93, $showDate, $black);
	imagestring($im, 5, 103, 129, $code, $black);
	$filename = TMP.$code.".jpg";
	imagejpeg ($im,$filename,100);
	return $filename;
}

function createPdfFileForId($resultSelected,$pdfName){
	try{
		$html2pdf = new HTML2PDF('P', 'A4', 'it');
		//$html2pdf->setModeDebug(true);
		$html2pdf->setDefaultFont('Arial');
		$html2pdf->writeHTML($resultSelected, isset($_GET['vuehtml']));
		if(file_exists($pdfName)){
			unlink($pdfName);
		}
		$html2pdf->Output($pdfName, 'F');
		//forceDownload($tempPdf);
	}catch(HTML2PDF_exception $e) {
		echo $e;
		exit;
	}
}

function forceDownload($path){
	$filename = $path;
	header("Pragma: public");
	header("Expires: 0"); // set expiration time
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header("Content-Type: application/force-download");
	header('Content-type: application/pdf');
	header("Content-Disposition: attachment; filename=".basename($filename).";");
	header("Content-Transfer-Encoding: binary");
	header("Content-Length: ".filesize($filename));
	readfile($path);
}


function reorderPrenos($prenos) {
	$result[] = array();
	$k = 0;
	$dateOfShow = returnDateOfShows(!current_user_can('manage_options'));
	foreach ($dateOfShow as $day) {
		$temp[$day['data']] = $k;
		$k++;
	}

	foreach ($prenos as $prenoPerDay) {
		if(isset($temp[$prenoPerDay['data']])){
			$result[$temp[$prenoPerDay['data']]][] = array('id' => $prenoPerDay['id'], 'name' => $prenoPerDay['nome'],'riferimento'=>$prenoPerDay['riferimento'],'prenocode'=>$prenoPerDay['prenocode']);
		}
	}

	return $result;
}

function foo() {
	if (isset($_POST['firstname']) && isset($_POST['id']) ) {
		$returned = updatePrenoName($_POST['firstname'], $_POST['id']);
	}
	echo $returned;
	die();
}

function theater_mb_delete() {
	if (isset($_POST['id'])) {
		$returned = deletePreno($_POST['id']);
	}
	echo $returned;
	die();
}

function transformDay($dayNumber) {
	switch ($dayNumber) {
		case 1:
			$giorno = 'Luned&igrave;';
			break;
		case 2:
			$giorno = 'Marted&igrave;';
			break;
		case 3:
			$giorno = 'Mercoled&igrave;';
			break;
		case 4:
			$giorno = 'Gioved&igrave;';
			break;
		case 5:
			$giorno = 'Venerd&igrave;';
			break;
		case 6:
			$giorno = 'Sabato';
			break;
		case 7:
			$giorno = 'Domenica';
			break;
	}
	return $giorno;
}

function builtInCondition($daysOfShow){
	$inCondition ="(";
	foreach($daysOfShow as $day){
		$inCondition .= $day['id'].",";
	}
	$inCondition =substr($inCondition,0,strlen($inCondition)-1);
	$inCondition .=")";
	return $inCondition;
}

function theater_mb_createHtlmForPage($isInModifyArea=false) {
	$widthOfFullTable =85;
	$daysOfShow = returnDateOfShows(!current_user_can('manage_options'));
	$inCondition= builtInCondition($daysOfShow);
	$prenos = reorderPrenos(returnPrenotDataTable($inCondition));
	$numberOfDays = count($daysOfShow);
	$numberOfInterval = floor($numberOfDays / 7);
	$rest = $numberOfDays % 7;
	$current_user = wp_get_current_user();
	$loginName= $current_user->user_login ;
	$maxSeatNumber = maximumNumberForAllShow($daysOfShow);
	$k=0;
	$TotBooked = 0;
	$TotFree = 0;
	for ($thisInterval = 0; $thisInterval < $numberOfInterval; $thisInterval++) {
		$output .= "
		<table border=\"1\" width=\"".$widthOfFullTable."%\" style=\"border:1px black;width:80%;border-collapse:collapse;\">
		<tr><td style=\"width:2%\">&nbsp;</td>";
		for ($p = $thisInterval * 7; $p < 7 * ($thisInterval + 1); $p++) {
			$date = new DateTime($daysOfShow[$p]['data']);
			$dayOfTheWheek = transformDay($date->format('N'));
			$dayOfTheShow = "<p>".$dayOfTheWheek . "</p><p>" . $date->format('d-m H:i')."</p>";
			$numberOfPrenotati = count($prenos[$k]);
			$freeSeat= $daysOfShow[$p]['posti']-$numberOfPrenotati;
			$output .="<td style=\"width:14%\">" . $dayOfTheShow ."<p>Prenotati:".$numberOfPrenotati. "</p><p>Liberi:".$freeSeat."</td>";
			$k++;
			$TotFree +=$freeSeat;
			$TotBooked+=$numberOfPrenotati;			
		}
		$output .= "</tr>
		";
		for ($i = 0; $i < $maxSeatNumber; $i++) {
			$contatore = $i + 1;
			$output .="<tr  id=\"" . $i . "\"><td style=\"width:2%\">" . $contatore . "</td>";
			for ($j = $thisInterval * 7; $j < 7 * ($thisInterval + 1); $j++) {
				if (isset($prenos[$j][$i])) {
					$editableCondition = is_user_logged_in() && $isInModifyArea && (($prenos[$j][$i]['riferimento']==$loginName && !isset($prenos[$j][$i]['prenocode']) )|| current_user_can('manage_options'));
					if ($editableCondition) {
						$output .="<td id=\"" . $prenos[$j][$i]['id'] . "\" class=\"edit_tr\" style=\"width:14%\">
						<span id=\"first_" . $prenos[$j][$i]['id'] . "\" class=\"text\">
						" . $prenos[$j][$i]['name'] . "</span>
						<input type=\"text\" value=\"" . $prenos[$j][$i]['name'] . "\" class=\"editbox\" id=\"first_input_" . $prenos[$j][$i]['id'] . "\"><p/><div id=\"hideaway_" . $prenos[$j][$i]['id'] . "\"  class=\"editbox\">
						<a id=\"intercept_" . $prenos[$j][$i]['id'] . "\"   class=\"info_link\">elimina</a></div>";
					} else {
						$output .="<td style=\"width:14%\">" . $prenos[$j][$i]['name'] . "</td>";
					}
				} else {
					$output .="<td style=\"width:14%\">&nbsp;</td>";
				}
			}
			$output .="</tr>
			";
		}
		$output .= "</table>
		";
	}
	if ($rest > 0) {
		$widthOfRestTable= floor(($widthOfFullTable*$rest)/7);
		$widthOfEach=round((100-2)/$rest);
		$output .= "
		<table border=\"1\" width=\"".$widthOfRestTable."%\" style=\"border:1px black;width:80%;border-collapse:collapse;\"><tr><td style=\"width:2%\">&nbsp;</td>";
		for ($p = $numberOfInterval * 7; $p < (7 * $numberOfInterval) + $rest; $p++) {
			$date = new DateTime($daysOfShow[$p]['data']);
			$dayOfTheWheek = transformDay($date->format('N'));
			$dayOfTheShow = "<p>".$dayOfTheWheek . "</p><p>" . $date->format('d-m H:i')."</p>";
			$numberOfPrenotati = count($prenos[$k]);
			$freeSeat= $daysOfShow[$p]['posti']-$numberOfPrenotati;
			$output .="<td style=\"width=\"".$widthOfEach."%\">" . $dayOfTheShow ."<p>Prenotati:".$numberOfPrenotati. "</p><p>Liberi:".$freeSeat."</td>";
			$k++;
			$TotFree +=$freeSeat;
			$TotBooked+=$numberOfPrenotati;
		}
		$output .= "</tr>
		";
		$start = $numberOfInterval * 7;
		$stop = $start + $rest;
		for ($i = 0; $i < $maxSeatNumber; $i++) {
			$contatore = $i + 1;
			$output .="<tr id=\"" . $i . "\" style=\"width:2%\"><td>" . $contatore . "</td>";
			for ($j = $start; $j < $stop; $j++) {
				if (isset($prenos[$j][$i])) {
					$editableCondition = is_user_logged_in() && $isInModifyArea && ($prenos[$j][$i]['riferimento']==$loginName || current_user_can('manage_options'));

					if ($editableCondition) {
						$output .="<td id=\"" . $prenos[$j][$i]['id'] . "\" class=\"edit_tr\" style=\"width:".$widthOfEach."%\"><span id=\"first_" . $prenos[$j][$i]['id'] . "\" class=\"text\">" . $prenos[$j][$i]['name'] . "</span><input type=\"text\" value=\"" . $prenos[$j][$i]['name'] . "\" class=\"editbox\" id=\"first_input_" . $prenos[$j][$i]['id'] . "\"><p/><div id=\"hideaway_" . $prenos[$j][$i]['id'] . "\"  class=\"editbox\"><a id=\"intercept_" . $prenos[$j][$i]['id'] . "\"   class=\"info_link\">elimina</a></div>";
					} else {
						$output .="<td style=\"width:".$widthOfEach."%\">" . $prenos[$j][$i]['name'] . "</td>";
					}
				} else {
					$output .="<td style=\"width:".$widthOfEach."%\">&nbsp;</td>";
				}
			}
			$output .="</tr>
			";
		}
		$output .= "</table>
		";
	}
	$TotalTextNumber ="<p>Totale posti Liberi: ".$TotFree."<p></p>Totale posti Prenotati: ".$TotBooked."</p>";
	return $TotalTextNumber.$output;
}

function maximumNumberForAllShow($showArray){
	$temp = array();
	foreach($showArray as $item){
		$temp[] =$item['posti'];
	}
	$result = count($temp)>0? max($temp):0; 
	return $result;
}

function createTableOfPrenoForOneDayForFrancy($allPrenosOfADay) {
	$numberOfPrenotati = count($allPrenosOfADay);
	$headerForUser = "<td>nome</td><td>riferimento</td><td>BIGLIETTO</td><td>Codice confema</td>";
	if (isset($allPrenosOfADay) && $numberOfPrenotati  > 0) {
		$iAmAdmin = current_user_can('manage_options');
		$numberOfAvailable =$allPrenosOfADay[0]['posti']-$numberOfPrenotati;
		$output .= "<table border=\"1\" style=\"border:1px black;width:95%;border-collapse:collapse;\">";
		$contatore = 1;
		$numberOfField = count($allPrenosOfADay[0]);
		$date = new DateTime($allPrenosOfADay[0]['data']);
		$dayOfTheWheek = transformDay($date->format('N'));
		$dayOfTheShow = $dayOfTheWheek . " " . $date->format('d-m H:i');
		$output .="<tr  id=\"title\"><td colspan=" . $numberOfField . ">Spettacolo di " . $dayOfTheShow . "</td>";
		$output .="</tr>			";
		$output .="<tr  id=\"0\"><td>&nbsp;</td>";
		$output .=$headerForUser;
		$output .="</tr>			";
		foreach ($allPrenosOfADay as $onePreno) {
			$output .="<tr  id=\"" . $i . "\"><td style=\"padding:3px;\">" . $contatore . "</td>";
			$dateIns = new DateTime($onePreno['_ins']);
			$dayOfIns = $dateIns->format('d-m H:i:s');
			$output .="<td style=\"padding:3px;\">" . $onePreno['nome'] . "</td><td style=\"padding:3px;\">" . $onePreno['riferimento'] . "</td><td style=\"padding:3px;width:200px;\">&nbsp;</td><td style=\"padding:3px;\">" . $onePreno['prenocode'] . "</td>";
			$output .="</tr>
			";
			$contatore++;
		}
		$output .= "</table>
		";
	}
	return $output;
}

function createTableOfPrenoForOneDay($allPrenosOfADay) {
	$moneyReserv = array();
	$numberOfPrenotati = count($allPrenosOfADay);
	$headerFoAdmin = "<td>nome</td><td>riferimento</td><td>Codice confema</td><td>Inserito il</td><td>Aggiornato il</td>";
	$headerForUser = "<td>nome</td><td>riferimento</td><td>BIGLIETTO</td><td>Codice confema</td>";
	if (isset($allPrenosOfADay) && $numberOfPrenotati  > 0) {
		$iAmAdmin = current_user_can('manage_options');
		$numberOfAvailable =$allPrenosOfADay[0]['posti']-$numberOfPrenotati;
		$output .= "<table border=\"1\" style=\"border:1px black;width:80%;border-collapse:collapse;\">";
		$contatore = 1;
		$numberOfField = count($allPrenosOfADay[0]);
		$date = new DateTime($allPrenosOfADay[0]['data']);
		$dayOfTheWheek = transformDay($date->format('N'));
		$dayOfTheShow = $dayOfTheWheek . " " . $date->format('d-m H:i');
		$output .="<tr  id=\"title\"><td colspan=" . $numberOfField . ">Spettacolo di " . $dayOfTheShow . "</td>";
		$output .="</tr>			";
		$output .="<tr  id=\"0\"><td>&nbsp;</td>";
		$tdText = $iAmAdmin?$headerFoAdmin:$headerForUser;
		$output .=$tdText;
		$output .="</tr>			";
		foreach ($allPrenosOfADay as $onePreno) {
			$output .="<tr  id=\"" . $i . "\"><td style=\"padding:3px;\">" . $contatore . "</td>";
			$dateIns = new DateTime($onePreno['_ins']);
			$dayOfIns = $dateIns->format('d-m H:i:s');
			if (isset($onePreno['_upd'])){
				$dateUpd = new DateTime($onePreno['_upd']);
				$dayOfUpd = $dateUpd->format('d-m H:i:s');
			}else{
				$dayOfUpd="&nbsp;";
				$havePayed ="&nbsp;";
			}
			if(isset($onePreno['prenocode'] )){
				$havePayed ="GI&Agrave; PAGATO";
				$moneyReserv[$onePreno['riferimento']]=$moneyReserv[$onePreno['riferimento']]+UNIQUE_TICKET_COST;
			}else {
				$havePayed = "RIDOTTO";
			}
			
			$adminText = "<td style=\"padding:3px;\">" . $onePreno['nome'] . "</td><td style=\"padding:3px;\">" . $onePreno['riferimento'] . "</td><td style=\"padding:3px;\">" . $onePreno['prenocode'] . "</td><td style=\"padding:3px;\">" . $dayOfIns . "</td><td style=\"padding:3px;\">" . $dayOfUpd . "</td>";
			$otherText ="<td style=\"padding:3px;\">" . $onePreno['nome'] . "</td><td style=\"padding:3px;\">" . $onePreno['riferimento'] . "</td><td style=\"padding:3px;\">" . $havePayed . "</td><td style=\"padding:3px;\">" . $onePreno['prenocode'] . "</td>";
			$tdText =$iAmAdmin?$adminText:$otherText;
			$output .=$tdText;
			$output .="</tr>
			";
			$contatore++;
		}
		$output .= "</table>
		";
	}
	if(isset($moneyReserv)){
		$totale=0;
		$detailMoneyOutput ="";
		foreach($moneyReserv as $key => $value){
			$detailMoneyOutput.=  "<p>".$key.": ".$value." &#8364;</p>";
			$totale+=$value;
		}
		$moneyOutput ="<p>Soldi gi&agrave; riscossi ".$totale."&#8364;. Dettaglio:</p>".$detailMoneyOutput;
	}
	return $moneyOutput.$output;
}

function teatro_modify() {
	if (isset($_POST['name']) && $_POST['name']!="" && isset($_POST['day']) && $_POST['f']=="i") {
		$userThatInsert=isset($_POST['useradmin'])?$_POST['useradmin']:$_POST['user'];
		insertInDb($_POST['name'], $userThatInsert, $_POST['day']);
	} elseif (isset($_POST['firstname']) && isset($_POST['id'])) {
		updatePrenoName($_POST['firstname'], $_POST['id']);
	}
	wp_enqueue_style("theater_style", get_bloginfo('wpurl') . "/wp-content/plugins/theater_preno/css/theater_style.css");

	$current_user = wp_get_current_user();
	$addPrenoHtml = "<div class=\"wrap\">
	<div id='icon-options-general' class='icon32'><br />
	</div>
	<h2>Prenotazioni</h2><br/><br/>
	Aggiungi prenotazione";
	$addPrenoHtml .="<form name=\"input\" method=\"post\" onsubmit=\"return sub()\" >
	<select name=\"day\">";
	$showsDay = returnDateOfShows(!current_user_can('manage_options'));
	for ($b = 0; $b < count($showsDay); $b++) {
		$date = new DateTime($showsDay[$b]['data']);
		$dayOfTheWheek = transformDay($date->format('N'));
		$dayOfTheShow = $dayOfTheWheek . " " . $date->format('d-m H:i');
		$addPrenoHtml .="<option value=\"" . $showsDay[$b]['data'] . "\">" . $dayOfTheShow . "</option>";
	}

	
	
	$addPrenoHtml .="</select>
	<input type=\"hidden\" name=\"f\" value=\"i\"/>
Nome: <input type=\"text\" name=\"name\" size=\"35\"/>";
 if(current_user_can('manage_options')){
		$allUsers = get_users();
		$addPrenoHtml .="Inserisci come:<select name=\"useradmin\">";
		foreach($allUsers as $user){
			if(strcasecmp(trim($current_user->user_login),trim($user->user_login)) == 0){
				$addPrenoHtml .="<option selected=\"selected\" value=\"" .  $user->user_login . "\">" .  $user->user_login. "</option>
				";
			}else{
				$addPrenoHtml .="<option value=\"" .  $user->user_login . "\">" .  $user->user_login. "</option>
				";
			}
		}
		$addPrenoHtml .="</select>";

	}
$addPrenoHtml .="<br />
<input type=\"submit\" value=\"Inserisci prenotazione\" />
 <input type=\"hidden\" name=\"user\" value=\"" . $current_user->user_login . "\">";



$addPrenoHtml .="</form>
</div>";
	$result = $addPrenoHtml . "<br/><br/>Puoi modificare/eliminare solo le prenotazioni inserite da te (quelle in rosso)<br/>" . theater_mb_createHtlmForPage(true);
	echo $result;
}

function teatro_mb_ticket(){
	$pdfForTicket="";
	if(isset($_POST['idsofbooked'])){
		$unserialyzed[] = array();
		$codeFiles = array();
		$printTicketFile ="";
		$stringOfGeneratedTicket ="";
		$cont =0;
		foreach ($_POST['idsofbooked'] as $idofOne){
			$unserialyzed =  unserialize(urldecode($idofOne));
			$code = isset($unserialyzed->prenocode)?$unserialyzed->prenocode:md5(uniqid("", true));
			updatePrenoWithGeneratedCode($unserialyzed->id,$unserialyzed->riferimento,$code);
			$codeFiles[$cont] = wp_teather_mb_writeOnTicket($unserialyzed->nome,$unserialyzed->riferimento,$code,$unserialyzed->data);
			$printTicketFile .= "<p><img src=\"".$codeFiles[$cont]."\"></p>
			";
			$stringOfGeneratedTicket .="<p>".$unserialyzed->nome."</p>";
			$cont++;
		}
		//$tmpNamePdf = "allticket.pdf";
		$tmpNamePdf = md5(uniqid("", true)).".pdf";
		$pdfFile=TMP.$tmpNamePdf;
		echo $printTicketFile;
		createPdfFileForId($printTicketFile,$pdfFile);
		foreach($codeFiles as $tmpImageFile){
			if(file_exists($tmpImageFile)){
				unlink($tmpImageFile);
			}
		}
		$pdfForTicket="<p>Generati biglietti per:</p>".$stringOfGeneratedTicket."<p><a href=\"".plugins_url( 'tmp/'.$tmpNamePdf , __FILE__ )."\" target=\"_blank\">Stampa i biglietti</a></p>";
	}
	$header = "<div class=\"wrap\">
	<div id='icon-options-general' class='icon32'><br />
	</div>
	<h2>Stampa Biglietti</h2><br/><br/>";
	$prenos = returnPrenotDataTable();
	$numberOfPrenotati = count($prenos);
	$current_user = wp_get_current_user();
	$loginName= $current_user->user_login ;
	if (isset($prenos) && $prenos > 0) {
		$output = "<form method=\"post\" name=\"inputticket\" onsubmit=\"return check_ticket_sub()\">";
		$output.="<table border=\"1\" style=\"border:1px black;width:80%;border-collapse:collapse;\">";
		$output .="<tr><td>&nbsp;</td><td>Giorno</td><td>Nome</td><td>Riferimento</td><td>Codice prenotazione</td></tr>";
		foreach($prenos as $onePreno){
			$date = new DateTime($allPrenosOfADay[0]['data']);
			$dayOfTheWheek = transformDay($date->format('N'));
			$dayOfTheShow = $dayOfTheWheek . " " . $date->format('d-m H:i');
			$printableCondition = is_user_logged_in() && ($onePreno['riferimento']==$loginName || current_user_can('manage_options'));
			if  ($printableCondition){
				$output .="<tr>";
				if (!isset($onePreno['prenocode'])){
				$informationToSerialyze = (object) array(
							    'id' => $onePreno['id'],
								'nome' => $onePreno['nome'],
							    'data' => $onePreno['data'],
								'riferimento' => $onePreno['riferimento'],
				);}else{
					$informationToSerialyze = (object) array(
										'id' => $onePreno['id'],
										'nome' => $onePreno['nome'],
										'data' => $onePreno['data'],
										'riferimento' => $onePreno['riferimento'],
										'prenocode' => $onePreno['prenocode']
					);
				}
				$serialized = urlencode(serialize($informationToSerialyze));
				$output .="<td><input type=\"checkbox\" name=\"idsofbooked[]\" value=\"".$serialized."\" ></td><td>".$onePreno['data']."</td><td>".$onePreno['nome']."</td><td>".$onePreno['riferimento']."</td><td>".$onePreno['prenocode']."</td>";
				$atleastOne=1;
				$output .="</tr>";
			}
		}
		$output .= "</table>";
		if(isset($atleastOne)){
			$output .="<input type=\"submit\" value=\"Genera i biglietti\">";
		}
		$output .= "</form>";
	}
	echo $header.$output.$pdfForTicket;
}

function teatro_mb_print() {
	$header = "<div class=\"wrap\">
	<div id='icon-options-general' class='icon32'><br />
	</div>
	<h2>Stampa Prenotazioni</h2><br/><br/>";
	$selectDay = "Stampa prenotazioni
	<form name=\"input\" method=\"post\">
	<select name=\"dayid\">";
	$showsDay = returnDateOfShows();
	for ($b = 0; $b < count($showsDay); $b++) {
		$date = new DateTime($showsDay[$b]['data']);
		$dayOfTheWheek = transformDay($date->format('N'));
		$dayOfTheShow = $dayOfTheWheek . " " . $date->format('d-m H:i');
		if(isset($_POST['dayid']) && $_POST['dayid'] ==$showsDay[$b]['id']  ){
			$selectDay .="<option value=\"" . $showsDay[$b]['id'] . "\" selected=\"selected\">" . $dayOfTheShow . "</option>";
		}else{
			$selectDay .="<option value=\"" . $showsDay[$b]['id'] . "\">" . $dayOfTheShow . "</option>";
		}
	}
	$selectDay .="</select> <input type=\"hidden\" name=\"function\" value=\"p\">
	<input type=\"submit\" value=\"Stampa lista delle prenotazioni\" />
	</form>
	</div><br/><br/>
	";
	$result = $header . $selectDay;

	if (isset($_POST['function']) && ($_POST['function']) == 'p' && isset($_POST['dayid'])) {
		$base=get_option('siteurl') . '/wp-admin/admin.php?page=teatro-mb-print';
		$allPrenosOfADay = returnPrenotDataTableForId($_POST['dayid']);
		$resultSelected = createTableOfPrenoForOneDay($allPrenosOfADay);
		$resultForFrancy = createTableOfPrenoForOneDayForFrancy($allPrenosOfADay);
		if(isset($resultSelected) && isset($resultForFrancy)){
			$arrayDataForOneShow = returnDataForSpettacoloId($_POST['dayid']);
			$date = new DateTime($arrayDataForOneShow[0]['data']);
			$dayOfTheWheek = transformDay($date->format('N'));
			$pdfName = html_entity_decode($arrayDataForOneShow[0]['nome'])." " . $date->format('d_m_Y-H_i').".pdf";
			createPdfFileForId($resultSelected,TMP.$pdfName);
			$result .="<a href=\"".plugin_dir_url(__FILE__)."tmp/".$pdfName."\" target=\"_blank\">Scaricami in PDF</a></br></br>";
			$pdfName = html_entity_decode($arrayDataForOneShow[0]['nome'])." " . $date->format('d_m_Y-H_i')."francy.pdf";
			createPdfFileForId($resultForFrancy,TMP.$pdfName);
			$result .="<a href=\"".plugin_dir_url(__FILE__)."tmp/".$pdfName."\" target=\"_blank\">Lista per il teatro</a></br></br>".$resultSelected;
		} else {
			$result .="<p>Nessun prenotato per questo show</p>";
		}
	}
	echo $result;
}

function teatro_mb_shows(){
	if(isset($_POST['id']) && isset($_POST['f']) && $_POST['f']=='u' 
			&& isset($_POST['timestamp'])
			&& isset($_POST['name']) && isset($_POST['location'])
			&& isset($_POST['details']) && isset($_POST['seats'])
			){
		updateShow($_POST['id'],$_POST['timestamp'],$_POST['name'],$_POST['location'],$_POST['details'],$_POST['seats']);
	}else if (isset($_POST['id']) && isset($_POST['f']) && $_POST['f']=='d'){
		deleteShow($_POST['id']);
	}else if (isset($_POST['f']) && $_POST['f']=='i' 
			&& isset($_POST['timestamp'])
			&& isset($_POST['namei']) && isset($_POST['locationi'])
			&& isset($_POST['detailsi']) && isset($_POST['seatsi'])
			){
		insertShow($_POST['timestamp'],$_POST['namei'],$_POST['locationi'],$_POST['detailsi'],$_POST['seatsi']);
	}
	$futureShow = retriveAllfutureShow();
	$content ="";
	$header = "<div class=\"wrap\">
	<div id='icon-options-general' class='icon32'><br />
	</div>
	<h2>Gestisci Spettacoli</h2><br/><br/>
	";
	$addshow = "<h3>Inserisci nuovo show</h3><br/>
	<table>
	<form name=\"addshow\" method=\"post\" onsubmit=\"return subAddShow()\">
	<input type=\"hidden\" name=\"f\" value= \"i\">
	<tr>
	<td>data</td><td>nome</td><td>Posto</td><td>dettagli</td><td>numero posti</td><td>&nbsp;</td>
	</tr>
	<tr>
	<td><input type=\"text\" name=\"timestamp\" value= \"".date("Y-m-d h:i:s",time())."\">
	<a href=\"javascript:show_calendar('document.addshow.timestamp', document.addshow.timestamp.value);\">
	<img src=\"".get_bloginfo('url').BASE_IMAGE_FOLDER_URL."cal.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"Click Here to Pick up the timestamp\"></a></td>
	<td><input type=\"text\" name=\"namei\" value= \"\"></td>
	<td><input type=\"text\" name=\"locationi\" value= \"\"></td>
	<td><input type=\"text\" name=\"detailsi\" value= \"\"></td>
	<td><input type=\"text\" name=\"seatsi\" value= \"\"></td>
	<td><input type=\"submit\" value=\"inserisci\" /></td>
	</tr>
	</form>
	</table>
	<br/><br/>";
	if(count($futureShow)>0){
		$content .="<h3>Modifica Show</h3></br>";
	}
	foreach ($futureShow as $oneShow){
		$content .="<form name=\"showdate".$oneShow['id']."\" method=\"post\">
		<input type=\"hidden\" name=\"id\" value= \"".$oneShow['id']."\">
		<input type=\"hidden\" name=\"f\" value= \"u\">
		";
		$content .="<input type=\"text\" name=\"timestamp\" value= \"".$oneShow['data']."\">
		<a href=\"javascript:show_calendar('document.showdate".$oneShow['id'].".timestamp', document.showdate".$oneShow['id'].".timestamp.value);\">
		<img src=\"".get_bloginfo('url').BASE_IMAGE_FOLDER_URL."cal.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"Click Here to Pick up the timestamp\"></a>";
		$content .="<input type=\"text\" name=\"name\" value= \"".$oneShow['nome']."\">
		";
		$content .="<input type=\"text\" name=\"location\" value= \"".$oneShow['luogo']."\">
		";
		$content .="<input type=\"text\" name=\"details\" value= \"".$oneShow['dettagli']."\">
		";
		
		$content .="<input type=\"text\" name=\"seats\" value= \"".$oneShow['posti']."\">
		<input type=\"submit\" value=\"Salva\" />
		</form>
		<form name=\"delete".$oneShow['id']."\" method=\"post\">
		<input type=\"hidden\" name=\"id\" value= \"".$oneShow['id']."\">
		<input type=\"hidden\" name=\"f\" value= \"d\">
		<input type=\"submit\" value=\"elimina\" />
		</form>
		<br/>";
	}
	echo $header.$addshow.$content;
}

function theater_mb_submenu() {

	if (function_exists('add_submenu_page')) {
		add_object_page('Teatro', 'Gestisci prenotazioni', 0, 'teatro-mb', 'teatro_modify', WP_PLUGIN_URL . '/theater_preno/img/theatre.png');
		$plugin_page = add_submenu_page('teatro-mb', 'Stampa prenotazioni', 'Stampa prenotazioni', 0, 'teatro-mb-print', 'teatro_mb_print');
		$plugin_page = add_submenu_page('teatro-mb', 'Biglietti', 'Stampa biglietti', 0, 'teatro-mb-ticket', 'teatro_mb_ticket');
		$plugin_page = add_submenu_page('teatro-mb', 'Gestisci spettacoli', 'Gestisci spettacoli', 'manage_options', 'teatro-mb-shows', 'teatro_mb_shows');
	}
}

add_action('admin_menu', 'theater_mb_submenu');
add_action('wp_ajax_foo', 'foo');
add_action('wp_ajax_delete', 'theater_mb_delete');
add_shortcode('mb_theater_preno', 'theater_mb_createHtlmForPage');
register_activation_hook(__FILE__, 'theater_mb_activate');
wp_enqueue_script('ajax_edittable', plugin_dir_url(__FILE__) . 'js/ajax_edittable.js', array('jquery'));
wp_enqueue_script('func',plugin_dir_url(__FILE__) . 'js/func.js');

?>