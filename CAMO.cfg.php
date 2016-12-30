;<?php
;die(); //For security
;/*
; Konfiguration für CAMO Reporting aus vereinsflieger.de

; hier login und passwort für das Einloggen in vereinsflieger angeben
;
[vereinsflieger]
login_name = "mail@adres.se"
passwort = "geheim"

;
; Liste der Flugzeuge, die in den CAMO Report einbezogen werden sollen. Liste von Kennzeichen mit Kommata getrennt
;
[verein]
flugzeuge = "D-1234,D-ABCD,D-XYZA"

;
; Programm-Modus: "daily" schickt die Fluege vom heutigen Tag an die CAMO, "lastmonth" die Fluege des letzten Monats.
;
[modus]
mode = "lastmonth"

;
; Konfiguration der gängisten variablen Einstellungen für den Mailversand
; wichtig bei receivers folgendes Format verwenden mailadresse:Name des Empfaengers
; mehrere solche Kombinationen sind möglich, wenn sie durch Kommata getrennt sind
; 
[mail]
smtp_server = "smtp.somewhere.com"
smtp_login = "userid"
smtp_passwd = "passwort"
from_address = "mail@verein.de"
from_name = "Segefliegerverein e.V."
receivers = "tb@pobox.com:Torsten Beyer,mail@camo.com:CAMO Manager"

;*/