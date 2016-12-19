<?php 
    
    // CAMO Reporting für vereinsflieger.de
    //
    // Dieses Skript ist dazu gedacht, 1x pro Tag zu laufen. Es lädt die Flüge von DIESEM Tag und generiert Bordbucheinträge für jedes Flugzeug, das in $LSVplanes aufgelistet ist
    // Diese Einträge landen in der Datei ./fluege.txt. Am Ende mailt dieses Skript die Datei an benannte Mailempfänger
    //
    // Wichtig: diese Skript nutzt die GetFlights_today() Funktion aus dem vereinsflieger wrapper. Es MUSS am selben Tag laufen, an dem die Flüge geflogen wurden.
    // Wenn man das nicht hinbekommt (z.B. weil die Flugerfassung so langsam ist), muss mann das Skript auf die Funktion umschreiben (auch im wrapper), die die Flüge eines
    // Tages ausliest.
    // Am besten lässt man dieses Skript Nachts (nach Sunset und Sunset-Bier) laufen. Wir lassen es um 23:45 laufen
    //
    // To do's:
    // - Datum für das reported werden soll konfigurierbar machen
    // Versionen
    // 0.1 - 28.10.16 erste Version
    // 0.2 - 31.10.16 umgestellt auf Konfigurationsdatei - keine IDs etc mehr im Code
    //
    // (c) Torsten Beyer, 2016
    
    $BASE_PATH = '/homepages/25/d558414169/htdocs/vereinsflieger/';
    
    $CONFIG_FILE = $BASE_PATH . "CAMOReport/CAMO.cfg.php";
    
    $aBBentry = array (
                "date" => "",
                "callsign" => "",
                "starttime" => "",
                "arrivaltime" => "",
                "flighttime" => 0,
                "blocktime" => 0,
                "motortime" => 0,
                "landingcount" => 0
    );
    $Flights = array();
    
    
    require_once($BASE_PATH . 'CAMOReport/VereinsfliegerRestInterface.php');
    require $BASE_PATH . 'PHPMailer/PHPMailerAutoload.php';
        
    $configuration = parse_ini_file ($CONFIG_FILE, 1);
    $CAMOPlanes = explode (",",$configuration['verein']['flugzeuge']);
    $mode = $configuration['modus']['mode'];
    	
    date_default_timezone_set ( "UTC");
    $a = new VereinsfliegerRestInterface();
    
    $result = $a->SignIn($configuration['vereinsflieger']['login_name'],$configuration['vereinsflieger']['passwort'],0);

    if ($result) {
        if ($mode=="lastmonth")
        {
          // get number of days in month
          $firstdayint = strtotime("first day of previous month");
          $dateArray = getdate($firstdayint);
          $max = cal_days_in_month(CAL_GREGORIAN, $dateArray['mon'], $dateArray['mday']);
          echo "Mode: flights from last month. Max days in last month: $max<br />";
        } else // mode: daily
        {
          echo "Mode: flights from today<br />";
          $max=1;  
        }
         
        // Loop through all days of last month
        for ($daycounter=0; $daycounter<=($max-1);$daycounter++)
        {
          if ($mode=="lastmonth")
          {
            // get first day of month
            $firstdayint = strtotime("first day of previous month");
            $firstday = date_create("@$firstdayint");
            $daydate = date_add($firstday, date_interval_create_from_date_string("$daycounter days"));
            $datum = date_format($daydate, "Y-m-d");
            echo "Date: $datum ";
            $return = $a->GetFlights_date ($datum);
          } else // mode: daily
          {
            $datum = date("d.m.Y", time());
            $return = $a->GetFlights_today();
          }
        
          
          if ($return) {
                  
              $aResponse = $a->GetResponse();
              $no_Flights = count ($aResponse) - 1; // das letzte Element ist httpresponse...
                
              //
              // alle Flüge sind in aResponse
              //
              
              if ($no_Flights > 0) {
                  for ($i=0; $i<$no_Flights;$i++) {
                    
                      $id = $aResponse[$i]["callsign"];
                      
                      $start = new DateTime($aResponse[$i]["departuretime"]);
                      $ende = new DateTime ($aResponse[$i]["arrivaltime"]);
                      
                      if (in_array($id, $CAMOPlanes)) {
                          
                          if (array_key_exists ($id,$Flights)) {
                              $Flights[$id]["flighttime"] += intval ($aResponse[$i]["flighttime"]);
                              $Flights[$id]["motortime"] += round((60.0 * ((floatval ($aResponse[$i]["motorend"])) - (floatval ($aResponse[$i]["motorstart"])))), 0);
                              $Flights[$id]["landingcount"] += intval ($aResponse[$i]["landingcount"]);
                              $Flights[$id]["blocktime"] += intval ($aResponse[$i]["blocktime"]);
                              
                              $temp_start = new DateTime($Flights[$id]["starttime"]);
                              $temp_end = new DateTime($Flights[$id]["arrivaltime"]);
                              
                              if ($start < $temp_start) {
                                  $Flights[$id]["starttime"] = $aResponse[$i]["departuretime"];
                              }
                              
                              if ($ende > $temp_end) {
                                  $Flights[$id]["arrivaltime"] = $aResponse[$i]["arrivaltime"];
                              }
                              
                              
                          }
                          else {
                              
                              $Flights[$id] = $aBBentry;
                              $Flights[$id]["callsign"] = $id;
                              $Flights[$id]["date"] = $datum;
                              $Flights[$id]["flighttime"] = intval ($aResponse[$i]["flighttime"]);
                              $Flights[$id]["motortime"] = round((60.0 * ((floatval ($aResponse[$i]["motorend"])) - (floatval ($aResponse[$i]["motorstart"])))), 0);
                              $Flights[$id]["landingcount"] = intval ($aResponse[$i]["landingcount"]);
                              $Flights[$id]["blocktime"] = intval ($aResponse[$i]["blocktime"]);
                              $Flights[$id]["starttime"] = $aResponse[$i]["departuretime"];
                              $Flights[$id]["arrivaltime"] = $aResponse[$i]["arrivaltime"];
                              
                          }
                      }
                  }
                  
              echo "some flights found<br />";
              } else {
                      echo ("no flights<br />");
        }
          }
          else {
              echo ("Flug lesen NAK<br />");
          }
       } //for days in month
       
       if (count($Flights) > 0)
       {
       
         $fp = fopen("fluege.txt","w");
         $subject = sprintf ("CAMO Report %s:\n", $datum);

         if ($mode=="lastmonth")
         { 
           fprintf ($fp,"CAMO Report %s:\n", $datum);
           fprintf ($fp,"Datum\t\tKennz.\t\tF-Zeit (min)\t\tMotorzeit(min)\t#Landungen\n");
                  
           foreach ($Flights as $entry) {
             fprintf ($fp, "%s\t%s\t\t%d\t\t\t%d\t\t%d\n", $entry["date"],$entry["callsign"],$entry["flighttime"],$entry["motortime"],$entry["landingcount"]);
           }
         } else 
         { // mode: daily
           fprintf ($fp,"CAMO Report %s:\n", $datum);
           fprintf ($fp,"Datum\t\tKennz.\tStart\tLandung\t\tF-Zeit (min)\tBlockzeit(min)\t#Landungen\n");
                
           foreach ($Flights as $entry) {
             fprintf ($fp, "%s\t%s\t%s\t%s\t\t%d\t\t%d\t\t\t%d\n", $entry["date"],$entry["callsign"],simpletime($entry["starttime"]), simpletime($entry["arrivaltime"]),$entry["flighttime"],$entry["blocktime"], $entry["landingcount"]);
           }
         }
         fclose ($fp);
                  
         //Create a new PHPMailer instance
         $mail = new PHPMailer;
         //Tell PHPMailer to use SendMail
         $mail->IsSendmail();
         //Enable SMTP debugging
         // 0 = off (for production use)
         // 1 = client messages
         // 2 = client and server messages
         $mail->SMTPDebug = 0;
         //Ask for HTML-friendly debug output
         $mail->Debugoutput = 'html';
         //Set the hostname of the mail server
         $mail->Host = gethostbyname($configuration['mail']['smtp_server']);
         // if your network does not support SMTP over IPv6
         //Set the SMTP port number - 587 for authenticated TLS, a.k.a. RFC4409 SMTP submission
         $mail->Port = 587;
         //Set the encryption system to use - ssl (deprecated) or tls
         $mail->SMTPSecure = 'tls';
         //Whether to use SMTP authentication
         $mail->SMTPAuth = true;
         //Username to use for SMTP authentication - use full email address for gmail
         $mail->Username = $configuration['mail']['smtp_login'];
         //Password to use for SMTP authentication
         $mail->Password = $configuration['mail']['smtp_passwd'];
         //Set who the message is to be sent from
         $mail->setFrom($configuration['mail']['from_address'], $configuration['mail']['from_name']);
         //Set an alternative reply-to address
         $mail->addReplyTo($configuration['mail']['from_address'], $configuration['mail']['from_name']);
         //Set who the message is to be sent to
       
         $receivers = explode (",", $configuration['mail']['receivers']);
         foreach ($receivers as $receiver) {
         
             $receiver_details = explode (":", $receiver);
             $mail->addAddress($receiver_details[0], $receiver_details[1]);
           
         }
         //Set the subject line
         $mail->Subject = $subject;
         $mail->Body = "Anbei der " . $subject . "\n\n";
         //Replace the plain text body with one created manually
         $mail->AltBody = 'This is a plain-text message body';
         //Attach an image file
         $mail->addAttachment('./fluege.txt');
         //send the message, check for errors
       
         if (!$mail->send()) {
             echo "Mailer Error: " . $mail->ErrorInfo;
         } else {
           echo "Mail sent<br />";
         }
       } else {
         echo "No flights, no mail sent<br />";
       }
          
    }
    else {
        echo ("Login failed<br />");
    }
    
    
    function simpletime ($timestring)
    {
        $start_date_elements = explode (" ", $timestring);
        $start_time_elements_UTC = explode ("+", $start_date_elements[1]);
        $start_time_elements = explode (":", $start_time_elements_UTC[0]);
        
        $seconds = intval($start_time_elements[2]);
        $minutes = intval($start_time_elements[1]);
        $hours = intval ($start_time_elements[0]);
        
        // skip seconds by rounding them
        if ($seconds >30) {
            $minutes +=1;
        }
        if ($minutes > 60) {
            $hours += 1;
        }
        
        $result = sprintf ("%'.02d:%'.02d", $hours, $minutes);
        
        return $result;
    }
    ?>
