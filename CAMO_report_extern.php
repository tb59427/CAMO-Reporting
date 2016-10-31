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
    // - Konfigurationsdaten aus dem Skript entfernen und in eine eigene Konfig-Datei auslagern
    // - Datum für das reported werden soll konfigurierbar machen
    // Version 0.1 - 28.10.16 erste Version
    //
    // (c) Torsten Beyer, 2016
    
    $aBBentry = array (
                "date" => "",
                "callsign" => "",
                "starttime" => "",
                "arrivaltime" => "",
                "flighttime" => 0,
                "blocktime" => 0,
                "landingcount" => 0
    );
    
    //
    // !!!Ändern!!!: hier die Liste der Vereinsflugzeuge eintragen - wenn bei Euch nur Vereinsflugzeuge fliegen, kannst man die markierte Stelle in der for Schleife umbauen
    //
    $LSVplanes = array ("D-EZIC","D-EAUO","D-KNAE","D-MSEF","D-2991","D-3695","D-8710","D-7655","D-0326", "D-0632","D-4239");
    $Flights = array();
    
    // !!!Ändern!!!: Login/Password für Anmeldung bei Vereinsflieger
    $VFlogin = "irgendwer@sonstwo.com";
    $VFpasswd = "geheim";
    //
    // !!!Ändern!!!: hier die absoluten Pfade zu den beiden inkludierten Klassen-Definitionen einfügen.
    // Das VereinsfliegerRestInterface gibt's zum herunterladen in vereinsflieger.de (Administration, dann ? und dann rumklicken)
    // PHPMailer gibt's auf git
    // beide irgendwo hinladen und die Pfade unten anpassen
    //
    require_once('/homepages/25/d558414169/htdocs/vereinsflieger/CAMOReport/VereinsfliegerRestInterface.php');
    require '/homepages/25/d558414169/htdocs/vereinsflieger/PHPMailer/PHPMailerAutoload.php';
    
    date_default_timezone_set ( "UTC");
    $a = new VereinsfliegerRestInterface();
    
    
        
    $result = $a->SignIn($VFlogin,$VFpasswd,0);

    if ($result) {
        
        $return = $a->GetFlights_today();
        if ($return) {
            
            
            $aResponse = $a->GetResponse();
            $no_Flights = count ($aResponse) - 1; // das letzte Element ist httpresponse...
            
            $timestamp = time();
            $datum = date("d.m.Y", $timestamp);
            
            //
            // alle Flüge sind in aResponse
            //
            
            // gab's überhaupt Flüge an dem Tag?
            //
            if ($no_Flights > 0) {
                
                // jo, es gab Flüge  also durch $aResponse durchhangeln Flug für Flug
                //
                for ($i=0; $i<$no_Flights;$i++) {
                    
                    $id = $aResponse[$i]["callsign"];
                    
                    $start = new DateTime($aResponse[$i]["departuretime"]);
                    $ende = new DateTime ($aResponse[$i]["arrivaltime"]);
                    
                    //
                    // Diese if Abfrage kann weg, wenn vereinsflieger nur Flüge von Flugzeugen meldet, die in der CAMO sind
                    // bei uns ist das aber nicht so, da wir säckeweise Privatflugzeuge haben
                    //
                    if (in_array($id, $LSVplanes)) {
                        
                        // haben wir schon einen Eintrag für dieses Flugzeug?
                        // Wenn ja, Daten hochzählen und schauen, ob dieser Flug vielleicht der früheste Start oder die letzte Landung war.
                        if (array_key_exists ($id,$Flights)) {
                            $Flights[$id]["flighttime"] += intval ($aResponse[$i]["flighttime"]);
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
                        //
                        // Nee, den gab's noch nicht - neu anlegen
                        //
                        else {
                            
                            $Flights[$id] = $aBBentry;
                            $Flights[$id]["callsign"] = $id;
                            $Flights[$id]["date"] = $datum;
                            $Flights[$id]["flighttime"] = intval ($aResponse[$i]["flighttime"]);
                            $Flights[$id]["landingcount"] = intval ($aResponse[$i]["landingcount"]);
                            $Flights[$id]["blocktime"] = intval ($aResponse[$i]["blocktime"]);
                            $Flights[$id]["starttime"] = $aResponse[$i]["departuretime"];
                            $Flights[$id]["arrivaltime"] = $aResponse[$i]["arrivaltime"];
                            
                        }
                    }
                }
                
                //
                // so, die Arbeit ist getan. Jetzt wegschreiben den Kram
                // Hier könnte man auch eine csv generieren (kann php off the shelf)
                //
                $fp = fopen("fluege.txt","w");
                $subject = sprintf ("CAMO Report %s:\n", $datum);
                
                fprintf ($fp,"CAMO Report %s:\n", $datum);
                fprintf ($fp,"Datum\t\tKennz.\tStart\tLandung\t\tF-Zeit (min)\tBlockzeit(min)\t#Landungen\n");
                
                foreach ($Flights as $entry) {
                    fprintf ($fp, "%s\t%s\t%s\t%s\t\t%d\t\t%d\t\t\t%d\n", $entry["date"],$entry["callsign"],simpletime($entry["starttime"]), simpletime($entry["arrivaltime"]),$entry["flighttime"],$entry["blocktime"], $entry["landingcount"]);
                }
                fclose ($fp);
                
                // So jetzt mailen den ganzen Kram
                //Create a new PHPMailer instance
                $mail = new PHPMailer;
                //Tell PHPMailer to use SendMail !!! ggfs. Ändern, je nachdem, welches Mailsystem auf dem Server installiert ist
                $mail->IsSendmail();
                //Enable SMTP debugging
                // 0 = off (for production use)
                // 1 = client messages
                // 2 = client and server messages
                $mail->SMTPDebug = 0;
                //Ask for HTML-friendly debug output
                $mail->Debugoutput = 'html';
                //Set the hostname of the mail server !!!Ändern!!!
                $mail->Host = gethostbyname('smtp.somwhere.com');
                // if your network does not support SMTP over IPv6
                //Set the SMTP port number - 587 for authenticated TLS, a.k.a. RFC4409 SMTP submission
                $mail->Port = 587;
                //Set the encryption system to use - ssl (deprecated) or tls
                $mail->SMTPSecure = 'tls';
                //Whether to use SMTP authentication
                $mail->SMTPAuth = true;
                //Username to use for SMTP authentication - use full email address for mailservice !!!Ändern!!!
                $mail->Username = "EinUserName";
                //Password to use for SMTP authentication !!!Ändern!!!
                $mail->Password = "geheim";
                //Set who the message is to be sent from !!!Ändern
                $mail->setFrom('jemand@deinclub.de', 'DeinClub e.V.');
                //Set an alternative reply-to address
                $mail->addReplyTo('jemand@deinclub.de', 'DeinClub e.V.');
                //Set who the message is to be sent to !!!Ändern!!! - wenn die Mail an mehrere gehen soll einfach mehrere Aufrufe an $mail->addAdress machen mit jeweils andern Adressen
                $mail->addAddress('jemand@deinecamo.com', 'Deine CAMO');
                
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
                }

            }
        }
        else {
            print_r ("Flug lesen NAK\n");
        }
    }
    else {
        print_r ("Login failed\n");
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
