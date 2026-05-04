(function(){

    'use strict';
  
  
  
    window.addEventListener("DOMContentLoaded", function(){
  
    // Container kontrolü
    
    const container = document.querySelector(".womosi-konfigurator-nf");
    
    if(!container) {
  
      console.warn("Womosi Konfigurator: Container nicht gefunden");
  
      return;
  
    }
  
    // URL für Maßanfertigung-Anfrage (Step-4-Fallback). Leer = mailto verwenden.
    // In deinem Shop liegt das PHP-Skript unter:
    // https://womosi.de/templates/NOVA/php/sitzbezug-anfrage.php
    // → daher hier der passende absolute Pfad (gleiche Domain).
    const WOMOSI_FALLBACK_SUBMIT_URL = "/sitzbezug-anfrage.php";
  
    /* ✓ AKTUALISIERT: Daten aus der neuen gpt.xlsx (eingebettet) */
    
    const RECORDS = [{"typ":"Integriert","hersteller":"Carthago","basis":"Carthago","modell":"c-tourer I / superior","sitz":"1+2","serie":"WMC/Carthago/555","link":"Carthago_2__c-tourer-I-superior_2__Integriert"},{"typ":"Integriert","hersteller":"Carthago","basis":"Carthago","modell":"chic c-line I","sitz":"1+2","serie":"WMC/Carthago/555","link":"Carthago_2__chic-c-line-I_2__Integriert"},{"typ":"Integriert","hersteller":"Carthago","basis":"Carthago","modell":"chic c-line superior I","sitz":"1+2","serie":"WMC/Carthago/555","link":"Carthago_2__chic-c-line-superior-I_2__Integriert"},{"typ":"Integriert","hersteller":"Carthago","basis":"Carthago","modell":"chic c-line xl I","sitz":"1+2","serie":"WMC/Carthago/555","link":"Carthago_2__chic-c-line-xl-I_2__Integriert"},{"typ":"Integriert","hersteller":"Carthago","basis":"Carthago","modell":"chic e-line I","sitz":"1+2","serie":"WMC/Carthago/555","link":"Carthago_2__chic-e-line-I_2__Integriert"},{"typ":"Integriert","hersteller":"Carthago","basis":"Carthago","modell":"chic s-plus I","sitz":"1+2","serie":"WMC/Carthago/555","link":"Carthago_2__chic-s-plus-I_2__Integriert"},{"typ":"Integriert","hersteller":"Carthago","basis":"Carthago","modell":"liner-for-two I","sitz":"1+2","serie":"WMC/Carthago/555","link":"Carthago_2__liner-for-two-I_2__Integriert"},{"typ":"Teilintegriert","hersteller":"Knaus","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"VAN TI","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Knaus_2__VAN-TI_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Adria","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Compact Axess Plus Supreme","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Adria_2__Compact-Axess-Plus-Supreme_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Adria","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Coral Axess Plus Supreme","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Adria_2__Coral-Axess-Plus-Supreme_2__Teilintegriert"},{"typ":"Alkoven","hersteller":"Adria","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Coral XL Axess Plus","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Adria_2__Coral-XL-Axess-Plus_2__Alkoven"},{"typ":"Teilintegriert","hersteller":"Adria","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Matrix Axess Plus Supreme","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Adria_2__Matrix-Axess-Plus-Supreme_2__Teilintegriert"},{"typ":"Kastenwagen","hersteller":"Adria","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Twin Axess Supreme Plus Sports","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Adria_2__Twin-Axess-Supreme-Plus-Sports_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Adria","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Twin Supreme","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Adria_2__Twin-Supreme_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Affinity","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"ONE DUO FIVE","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Affinity_2__ONE-DUO-FIVE_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Benimar","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Benivan","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Benimar_2__Benivan_2__Kastenwagen"},{"typ":"Alkoven","hersteller":"Benimar","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Sport","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Benimar_2__Sport_2__Alkoven"},{"typ":"Teilintegriert","hersteller":"Benimar","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Tessoro D","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Benimar_2__Tessoro-D_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Benimar","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Mileo","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Benimar_2__Mileo_2__Teilintegriert"},{"typ":"Kastenwagen","hersteller":"Bürstner","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Nexxo Van","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Buerstner_2__Nexxo-Van_2__Kastenwagen"},{"typ":"Teilintegriert","hersteller":"Bürstner","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Limited T","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Buerstner_2__Limited-T_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Bürstner","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Lyseo TD","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Buerstner_2__Lyseo-TD_2__Teilintegriert"},{"typ":"Alkoven","hersteller":"Bürstner","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Lyseo Gallery TD","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Buerstner_2__Lyseo-Gallery-TD_2__Alkoven"},{"typ":"Teilintegriert","hersteller":"Bürstner","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Lyseo Harmony Line","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Buerstner_2__Lyseo-Harmony-Line_2__Teilintegriert"},{"typ":"Kastenwagen","hersteller":"Bürstner","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Campeo","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Buerstner_2__Campeo_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Bürstner","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Eliseo Active","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Buerstner_2__Eliseo-Active_2__Kastenwagen"},{"typ":"Alkoven","hersteller":"Bürstner","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Lyseo Time","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Buerstner_2__Lyseo-Time_2__Alkoven"},{"typ":"Teilintegriert","hersteller":"Bürstner","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Ixeo I / Time","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Buerstner_2__Ixeo-I-Time_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Carado","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"T 447","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Carado_2__T-447_2__Teilintegriert"},{"typ":"Alkoven","hersteller":"Carado","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"A 461","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Carado_2__A-461_2__Alkoven"},{"typ":"Teilintegriert","hersteller":"Carado","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"T 132","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Carado_2__T-132_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Carado","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"T 447H","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Carado_2__T-447H_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Carado","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"T 457 Pro+","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Carado_2__T-457-Pro_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Carado","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"T 337","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Carado_2__T-337_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Carado","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"T 448","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Carado_2__T-448_2__Teilintegriert"},{"typ":"Alkoven","hersteller":"Carado","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"A 132","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Carado_2__A-132_2__Alkoven"},{"typ":"Teilintegriert","hersteller":"Carado","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"T 449","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Carado_2__T-449_2__Teilintegriert"},{"typ":"Alkoven","hersteller":"Carado","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"A 361","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Carado_2__A-361_2__Alkoven"},{"typ":"Alkoven","hersteller":"Carado","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"A 464","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Carado_2__A-464_2__Alkoven"},{"typ":"Teilintegriert","hersteller":"Carado","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"T 135","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Carado_2__T-135_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Carado","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"T 338","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Carado_2__T-338_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Carado","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"T 459","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Carado_2__T-459_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Carado","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"T 334","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Carado_2__T-334_2__Teilintegriert"},{"typ":"Kastenwagen","hersteller":"Carado","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Edition15 CV","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Carado_2__Edition15-CV_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Carado","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Clever+ CV","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Carado_2__Clever-CV_2__Kastenwagen"},{"typ":"Teilintegriert","hersteller":"Carado","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Edition15 T","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Carado_2__Edition15-T_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Carado","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Europa V","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Carado_2__Europa-V_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Carado","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"V 337","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Carado_2__V-337_2__Teilintegriert"},{"typ":"Alkoven","hersteller":"Carado","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Pro A","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Carado_2__Pro-A_2__Alkoven"},{"typ":"Kastenwagen","hersteller":"Carado","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Pro CV","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Carado_2__Pro-CV_2__Kastenwagen"},{"typ":"Teilintegriert","hersteller":"Carado","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Pro V","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Carado_2__Pro-V_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Carthago","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"c-tourer T","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Carthago_2__c-tourer-T_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Challenger","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Alkoven C397GA Start Edition","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Challenger_2__Alkoven-C397GA-Start-Edition_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Challenger","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Teilintegriert 337GA Ultimate Premium","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Challenger_2__Teilintegriert-337GA-Ultimate-Premium_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Challenger","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Teilintegriert 384","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Challenger_2__Teilintegriert-384_2__Teilintegriert"},{"typ":"Kastenwagen","hersteller":"Challenger","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Van Sport Edition","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Challenger_2__Van-Sport-Edition_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Challenger","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Van Start Edition","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Challenger_2__Van-Start-Edition_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Challenger","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Vany","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Challenger_2__Vany_2__Kastenwagen"},{"typ":"Alkoven","hersteller":"Challenger","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"X 150 X 250 Open Edition","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Challenger_2__X-150-X-250-Open-Edition_2__Alkoven"},{"typ":"Kastenwagen","hersteller":"Chausson","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Kastenwagen Van","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Chausson_2__Kastenwagen-Van_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Chausson","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Twist Van V594 MAX","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Chausson_2__Twist-Van-V594-MAX_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Chausson","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"First line sport line","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Chausson_2__First-line-sport-line_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Chausson","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"X exclusive line","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Chausson_2__X-exclusive-line_2__Kastenwagen"},{"typ":"Teilintegriert","hersteller":"Chausson","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"etape line","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Chausson_2__etape-line_2__Teilintegriert"},{"typ":"Kastenwagen","hersteller":"Clever Vans","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Celebration","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Clever-Vans_2__Celebration_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Clever Vans","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Drive","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Clever-Vans_2__Drive_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Clever Vans","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Duo","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Clever-Vans_2__Duo_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Clever Vans","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Runner","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Clever-Vans_2__Runner_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Clever Vans","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Spirit","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Clever-Vans_2__Spirit_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Clever Vans","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Tour Duo","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Clever-Vans_2__Tour-Duo_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Clever Vans","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Vario KIDS","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Clever-Vans_2__Vario-KIDS_2__Kastenwagen"},{"typ":"Teilintegriert","hersteller":"Dethleffs","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Globebus T1","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Dethleffs_2__Globebus-T1_2__Teilintegriert"},{"typ":"Kastenwagen","hersteller":"Dethleffs","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Globetrail Classic Advantage","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Dethleffs_2__Globetrail-Classic-Advantage_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Dethleffs","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Globetrail Classic Advantage","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Dethleffs_2__Globetrail-Classic-Advantage_2__Kastenwagen"},{"typ":"Teilintegriert","hersteller":"Dethleffs","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Just Camp","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Dethleffs_2__Just-Camp_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Dethleffs","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Trend T","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Dethleffs_2__Trend-T_2__Teilintegriert"},{"typ":"Kastenwagen","hersteller":"Dreamer","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Fun","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Dreamer_2__Fun_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Dreamer","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Fun Camper Five","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Dreamer_2__Fun-Camper-Five_2__Kastenwagen"},{"typ":"Urban-Camper","hersteller":"Dreamer","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Select Camper Can XL","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Dreamer_2__Select-Camper-Can-XL_2__Urban-Camper"},{"typ":"Kastenwagen","hersteller":"Dreamer","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Select D55+ D62 Limited D68","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Dreamer_2__Select-D55-D62-Limited-D68_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Dreamer","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Select Family Van","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Dreamer_2__Select-Family-Van_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Dreamer","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Select LIVING VAN","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Dreamer_2__Select-LIVING-VAN_2__Kastenwagen"},{"typ":"Alkoven","hersteller":"Etrusco","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"A Modell A 7300 DB","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Etrusco_2__A-Modell-A-7300-DB_2__Alkoven"},{"typ":"Kastenwagen","hersteller":"Etrusco","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"CV Modell","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Etrusco_2__CV-Modell_2__Kastenwagen"},{"typ":"Teilintegriert","hersteller":"Etrusco","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"T Modell","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Etrusco_2__T-Modell_2__Teilintegriert"},{"typ":"Alkoven","hersteller":"Eura Mobil","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Activa One","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Eura-Mobil_2__Activa-One_2__Alkoven"},{"typ":"Teilintegriert","hersteller":"Eura Mobil","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Profila RS","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Eura-Mobil_2__Profila-RS_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Eura Mobil","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Profila T","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Eura-Mobil_2__Profila-T_2__Teilintegriert"},{"typ":"Kastenwagen","hersteller":"Eura Mobil","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Van","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Eura-Mobil_2__Van_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Fiat","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Ducato 250 Sun Camper Hunter 630 Maxi","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Fiat_2__Ducato-250-Sun-Camper-Hunter-630-Maxi_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Fiat","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Ducato 250  FurgoK 600 LANDSBERG","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Fiat_2__Ducato-250-FurgoK-600-LANDSBERG_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Fiat","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Ducato 250 Cruiser Line 640","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Fiat_2__Ducato-250-Cruiser-Line-640_2__Kastenwagen"},{"typ":"Alkoven","hersteller":"Forster","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Alkoven","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Forster_2__Alkoven_2__Alkoven"},{"typ":"Teilintegriert","hersteller":"Forster","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Teilintegriert","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Forster_2__Teilintegriert_2__Teilintegriert"},{"typ":"Kastenwagen","hersteller":"Forster","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Livin up Vans","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Forster_2__Livin-up-Vans_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Forster","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Yongster Vans V 541","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Forster_2__Yongster-Vans-V-541_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Forster","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Vantasy Vans","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Forster_2__Vantasy-Vans_2__Kastenwagen"},{"typ":"Alkoven","hersteller":"Frankia","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"F-Line","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Frankia_2__F-Line_2__Alkoven"},{"typ":"Alkoven","hersteller":"Frankia","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Yucon D","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Frankia_2__Yucon-D_2__Alkoven"},{"typ":"Teilintegriert","hersteller":"GiottiLine","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Compact","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"GiottiLine_2__Compact_2__Teilintegriert"},{"typ":"Kastenwagen","hersteller":"GiottiLine","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"GiottiVan 50T 60B","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"GiottiLine_2__GiottiVan-50T-60B_2__Kastenwagen"},{"typ":"Teilintegriert","hersteller":"GiottiLine","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Siena A","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"GiottiLine_2__Siena-A_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"GiottiLine","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Siena","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"GiottiLine_2__Siena_2__Teilintegriert"},{"typ":"Kastenwagen","hersteller":"Globecar","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Campscout Elegance","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Globecar_2__Campscout-Elegance_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Globecar","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Globescout R Elegance","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Globecar_2__Globescout-R-Elegance_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Globecar","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Summit Prime","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Globecar_2__Summit-Prime_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Globecar","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Summit Shine","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Globecar_2__Summit-Shine_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Globecar","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Vario","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Globecar_2__Vario_2__Kastenwagen"},{"typ":"Teilintegriert","hersteller":"Hobby","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Optima De Luxe","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Hobby_2__Optima-De-Luxe_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Hobby","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Optima ONTOUR","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Hobby_2__Optima-ONTOUR_2__Teilintegriert"},{"typ":"Alkoven","hersteller":"Hobby","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Optima ONTOUR ALKOVEN","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Hobby_2__Optima-ONTOUR-ALKOVEN_2__Alkoven"},{"typ":"Teilintegriert","hersteller":"Hobby","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Optima ONTOUR Edition","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Hobby_2__Optima-ONTOUR-Edition_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Hobby","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Optima ONTOUR Edition F","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Hobby_2__Optima-ONTOUR-Edition-F_2__Teilintegriert"},{"typ":"Kastenwagen","hersteller":"Hobby","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Vantana De Luxe","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Hobby_2__Vantana-De-Luxe_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Hobby","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Vantana ONTOUR EDITION","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Hobby_2__Vantana-ONTOUR-EDITION_2__Kastenwagen"},{"typ":"Teilintegriert","hersteller":"Hymer","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Exsis-t Pure Original","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Hymer_2__Exsis-t-Pure-Original_2__Teilintegriert"},{"typ":"Kastenwagen","hersteller":"Hymer","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Ayers Rock","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Hymer_2__Ayers-Rock_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Hymer","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Grand Canyon","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Hymer_2__Grand-Canyon_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Hymer","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Yosemite","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Hymer_2__Yosemite_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Hymer","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Free","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Hymer_2__Free_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Hymer","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Free Blue Evolution","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Hymer_2__Free-Blue-Evolution_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Hymer","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Sydney","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Hymer_2__Sydney_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Hymer","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Car GT Line","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Hymer_2__Car-GT-Line_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Hymer","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"B584 B 524 544 B644 E 690","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Hymer_2__B584-B-524-544-B644-E-690_2__Kastenwagen"},{"typ":"Teilintegriert","hersteller":"Kabe","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Travel Master Classic","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Kabe_2__Travel-Master-Classic_2__Teilintegriert"},{"typ":"Kastenwagen","hersteller":"Karmann","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Davis Trendstyle","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Karmann_2__Davis-Trendstyle_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Karmann","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Davis Lifestyle","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Karmann_2__Davis-Lifestyle_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Karmann","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Dexter 550","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Karmann_2__Dexter-550_2__Kastenwagen"},{"typ":"Teilintegriert","hersteller":"Knaus","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"L!VE LIVE TI","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Knaus_2__LVE-LIVE-TI_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Knaus","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"SKY TI","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Knaus_2__SKY-TI_2__Teilintegriert"},{"typ":"Kastenwagen","hersteller":"Knaus","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"BOXLIFE PRO / XL Camper Van","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Knaus_2__BOXLIFE-PRO-XL-Camper-Van_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Knaus","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"BOXLIFE","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Knaus_2__BOXLIFE_2__Kastenwagen"},{"typ":"Alkoven","hersteller":"Knaus","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Sky / Sun / Live Traveller","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Knaus_2__Sky-Sun-Live-Traveller_2__Alkoven"},{"typ":"Kastenwagen","hersteller":"Knaus","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"BoxStar","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Knaus_2__BoxStar_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Laika","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Ecovip / Kosmo","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Laika_2__Ecovip-Kosmo_2__Kastenwagen"},{"typ":"Teilintegriert","hersteller":"Laika","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Ecovip","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Laika_2__Ecovip_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Laika","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Kosmo / Kreos","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Laika_2__Kosmo-Kreos_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"La Marca","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Ducato Jumper Boxer","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"La-Marca_2__Ducato-Jumper-Boxer_2__Teilintegriert"},{"typ":"Kastenwagen","hersteller":"Malibu","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"VAN FIRST CLASS / TWO ROOMS","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Malibu_2__VAN-FIRST-CLASS-TWO-ROOMS_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Malibu","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"VAN DIVERSITY","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Malibu_2__VAN-DIVERSITY_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Malibu","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Comfort","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Malibu_2__Comfort_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Malibu","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Compact","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Malibu_2__Compact_2__Kastenwagen"},{"typ":"Teilintegriert","hersteller":"Malibu","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"T","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Malibu_2__T_2__Teilintegriert"},{"typ":"Integriert","hersteller":"Malibu","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"I","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Malibu_2__I_2__Integriert"},{"typ":"Kastenwagen","hersteller":"LMC","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Innovan","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"LMC_2__Innovan_2__Kastenwagen"},{"typ":"Teilintegriert","hersteller":"LMC","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Tourer / Lift","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"LMC_2__Tourer-Lift_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"LMC","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Komfort / Cruiser","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"LMC_2__Komfort-Cruiser_2__Teilintegriert"},{"typ":"Alkoven","hersteller":"LMC","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Tourer A Breezer Sportline","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"LMC_2__Tourer-A-Breezer-Sportline_2__Alkoven"},{"typ":"Teilintegriert","hersteller":"LMC","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Breezer Lift","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"LMC_2__Breezer-Lift_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"LMC","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Breezer","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"LMC_2__Breezer_2__Teilintegriert"},{"typ":"Kastenwagen","hersteller":"Mobilvetta","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Admiral","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Mobilvetta_2__Admiral_2__Kastenwagen"},{"typ":"Teilintegriert","hersteller":"Mobilvetta","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Kea P","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Mobilvetta_2__Kea-P_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Mobilvetta","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Krosser","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Mobilvetta_2__Krosser_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"McLouis","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"MC4 250","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"McLouis_2__MC4-250_2__Teilintegriert"},{"typ":"Alkoven","hersteller":"McLouis","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Glamys","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"McLouis_2__Glamys_2__Alkoven"},{"typ":"Kastenwagen","hersteller":"McLouis","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Menfys / S-Line / Prestige","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"McLouis_2__Menfys-S-Line-Prestige_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Mooveo","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Van Ducato","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Mooveo_2__Van-Ducato_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Mooveo","basis":"Ford Transit","modell":"Van Transit","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Mooveo_2__Van-Transit_2__Kastenwagen"},{"typ":"Teilintegriert","hersteller":"Mooveo","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"TEI Ducato","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Mooveo_2__TEI-Ducato_2__Teilintegriert"},{"typ":"Kastenwagen","hersteller":"Pilote","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Vega","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Pilote_2__Vega_2__Kastenwagen"},{"typ":"Teilintegriert","hersteller":"Pilote","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Pacific","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Pilote_2__Pacific_2__Teilintegriert"},{"typ":"Kastenwagen","hersteller":"Pössl","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"D Line","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Poessl_2__D-Line_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Pössl","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Summit","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Poessl_2__Summit_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Pössl","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Summit Prime","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Poessl_2__Summit-Prime_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Pössl","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Summit Shine","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Poessl_2__Summit-Shine_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Pössl","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"2WIN PLUS","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Poessl_2__2WIN-PLUS_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Pössl","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Trenta","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Poessl_2__Trenta_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Pössl","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"H-Line","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Poessl_2__H-Line_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Rapido","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Vans","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Rapido_2__Vans_2__Kastenwagen"},{"typ":"Teilintegriert","hersteller":"Rapido","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Serie C","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Rapido_2__Serie-C_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Rapido","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Serie 6F","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Rapido_2__Serie-6F_2__Teilintegriert"},{"typ":"Kastenwagen","hersteller":"Roadcar","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"R540, R600, R601, R640","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Roadcar_2__R540-R600-R601-R640_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Roller Team","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Livingstone","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Roller-Team_2__Livingstone_2__Kastenwagen"},{"typ":"Teilintegriert","hersteller":"Roller Team","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Fiat Kronos TI","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Roller-Team_2__Fiat-Kronos-TI_2__Teilintegriert"},{"typ":"Alkoven","hersteller":"Roller Team","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Fiat Kronos Alkoven","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Roller-Team_2__Fiat-Kronos-Alkoven_2__Alkoven"},{"typ":"Teilintegriert","hersteller":"Adria","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Coral Adriatik Silver","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Adria_2__Coral-Adriatik-Silver_2__Teilintegriert"},{"typ":"Kastenwagen","hersteller":"Adria","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Twin Club Maxivan","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Adria_2__Twin-Club-Maxivan_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Bravia","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Swan 599 Trend Edition 636","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Bravia_2__Swan-599-Trend-Edition-636_2__Kastenwagen"},{"typ":"Alkoven","hersteller":"Challenger","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Gensis 53","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Challenger_2__Gensis-53_2__Alkoven"},{"typ":"Alkoven","hersteller":"Challenger","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Mageo","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Challenger_2__Mageo_2__Alkoven"},{"typ":"Teilintegriert","hersteller":"Eura Mobil","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Terrestra","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Eura-Mobil_2__Terrestra_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Knaus","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Sky Wave","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Knaus_2__Sky-Wave_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Fiat","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Ducato Jumper Boxer","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Fiat_2__Ducato-Jumper-Boxer_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Adria","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Coral Compact","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Adria_2__Coral-Compact_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Adria","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Coral Sport","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Adria_2__Coral-Sport_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Carado","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"T 348","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Carado_2__T-348_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Carado","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"T 138","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Carado_2__T-138_2__Teilintegriert"},{"typ":"Alkoven","hersteller":"Carado","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"A 160","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Carado_2__A-160_2__Alkoven"},{"typ":"Teilintegriert","hersteller":"Carado","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"T 345","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Carado_2__T-345_2__Teilintegriert"},{"typ":"Alkoven","hersteller":"Dethleffs","basis":"Iveco Daily","modell":"Globetrotter XXL","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Dethleffs_2__Globetrotter-XXL_2__Alkoven"},{"typ":"Kastenwagen","hersteller":"La Strada","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Avanti","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"La-Strada_2__Avanti_2__Kastenwagen"},{"typ":"Alkoven","hersteller":"Dethleffs","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Advantage A","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Dethleffs_2__Advantage-A_2__Alkoven"},{"typ":"Alkoven","hersteller":"Hobby","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Siesta Alkoven","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Hobby_2__Siesta-Alkoven_2__Alkoven"},{"typ":"Teilintegriert","hersteller":"Hobby","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Siesta V 60 GF","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Hobby_2__Siesta-V-60-GF_2__Teilintegriert"},{"typ":"nan","hersteller":"LMC","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Liberty Cruiser Energy Breezer","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"LMC_2__Liberty-Cruiser-Energy-Breezer_2__nan"},{"typ":"Teilintegriert","hersteller":"Challenger","basis":"Peugeot Boxer","modell":"Etape Edition","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Challenger_2__Etape-Edition_2__Teilintegriert"},{"typ":"Alkoven","hersteller":"Dethleffs","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Alpa A","sitz":"3+5","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Dethleffs_2__Alpa-A_2__Alkoven"},{"typ":"Integriert","hersteller":"Carthago","basis":"Carthago","modell":"c-compactline I","sitz":"6","serie":"AH/GT","link":"Carthago_2__c-compactline-I_2__Integriert"},{"typ":"Teilintegriert","hersteller":"Carthago","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"chic c-line T","sitz":"6","serie":"AH/GT","link":"Carthago_2__chic-c-line-T_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Eura Mobil","basis":"Mercedes Sprinter","modell":"Profila T","sitz":"6+9+10","serie":"AH/GT/PLKT/WPL/WMS/C/H/DPL/T","link":"Eura-Mobil_2__Profila-T_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Eura Mobil","basis":"Mercedes Sprinter","modell":"Contura","sitz":"6+9+10","serie":"AH/GT/PLKT/WPL/WMS/C/H/DPL/T","link":"Eura-Mobil_2__Contura_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Bürstner","basis":"Mercedes Sprinter","modell":"Lyseo M Harmony Line","sitz":"6+9+10","serie":"AH/GT/PLKT/WPL/WMS/C/H/DPL/T","link":"Buerstner_2__Lyseo-M-Harmony-Line_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Carthago","basis":"Mercedes Sprinter","modell":"c-tourer T","sitz":"6+9+10","serie":"AH/GT/PLKT/WPL/WMS/C/H/DPL/T","link":"Carthago_2__c-tourer-T_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Carthago","basis":"Mercedes Sprinter","modell":"chic c-line T","sitz":"6+9+10","serie":"AH/GT/PLKT/WPL/WMS/C/H/DPL/T","link":"Carthago_2__chic-c-line-T_2__Teilintegriert"},{"typ":"Alkoven","hersteller":"Frankia","basis":"Mercedes Sprinter","modell":"Yucon S","sitz":"6+9+10","serie":"AH/GT/PLKT/WPL/WMS/C/H/DPL/T","link":"Frankia_2__Yucon-S_2__Alkoven"},{"typ":"Kastenwagen","hersteller":"Bravia","basis":"Mercedes Sprinter","modell":"Swan 699","sitz":"6+9+10","serie":"AH/GT/PLKT/WPL/WMS/C/H/DPL/T","link":"Bravia_2__Swan-699_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Kabe","basis":"Mercedes Sprinter","modell":"Travel Master VAN","sitz":"6+9+10","serie":"AH/GT/PLKT/WPL/WMS/C/H/DPL/T","link":"Kabe_2__Travel-Master-VAN_2__Kastenwagen"},{"typ":"Teilintegriert","hersteller":"Adria","basis":"Mercedes Sprinter","modell":"Coral Supreme","sitz":"6+9+10","serie":"AH/GT/PLKT/WPL/WMS/C/H/DPL/T","link":"Adria_2__Coral-Supreme_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Adria","basis":"Mercedes Sprinter","modell":"Matrix Supreme","sitz":"6+9+10","serie":"AH/GT/PLKT/WPL/WMS/C/H/DPL/T","link":"Adria_2__Matrix-Supreme_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Kabe","basis":"Mercedes Sprinter","modell":"Travel Master Novum","sitz":"6+9+10","serie":"AH/GT/PLKT/WPL/WMS/C/H/DPL/T","link":"Kabe_2__Travel-Master-Novum_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Kabe","basis":"Mercedes Sprinter","modell":"Travel Master Royal","sitz":"6+9+10","serie":"AH/GT/PLKT/WPL/WMS/C/H/DPL/T","link":"Kabe_2__Travel-Master-Royal_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Eura Mobil","basis":"Mercedes Sprinter","modell":"Xtura","sitz":"6+9+10","serie":"AH/GT/PLKT/WPL/WMS/C/H/DPL/T","link":"Eura-Mobil_2__Xtura_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Frankia","basis":"Mercedes Sprinter","modell":"Neo","sitz":"6+9+10","serie":"AH/GT/PLKT/WPL/WMS/C/H/DPL/T","link":"Frankia_2__Neo_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Frankia","basis":"Mercedes Sprinter","modell":"M-LINE","sitz":"6+9+10","serie":"AH/GT/PLKT/WPL/WMS/C/H/DPL/T","link":"Frankia_2__M-LINE_2__Teilintegriert"},{"typ":"Integriert","hersteller":"Adria","basis":"Adria","modell":"Sonic Plus","sitz":"7","serie":"AH/GT","link":"Adria_2__Sonic-Plus_2__Integriert"},{"typ":"Integriert","hersteller":"Adria","basis":"Adria","modell":"Sonic Supreme","sitz":"7","serie":"AH/GT","link":"Adria_2__Sonic-Supreme_2__Integriert"},{"typ":"Integriert","hersteller":"Adria","basis":"Adria","modell":"Vision","sitz":"7","serie":"AH/GT","link":"Adria_2__Vision_2__Integriert"},{"typ":"Integriert","hersteller":"Forster","basis":"Forster","modell":"Integriert","sitz":"7","serie":"AH/GT","link":"Forster_2__Integriert_2__Integriert"},{"typ":"Integriert","hersteller":"Bürstner","basis":"Bürstner","modell":"Elegance I","sitz":"7","serie":"AH/GT","link":"Buerstner_2__Elegance-I_2__Integriert"},{"typ":"Integriert","hersteller":"Bürstner","basis":"Bürstner","modell":"Aviano","sitz":"7","serie":"AH/GT","link":"Buerstner_2__Aviano_2__Integriert"},{"typ":"Integriert","hersteller":"Carado","basis":"Carado","modell":"I 449 338 449 pro+","sitz":"7","serie":"AH/GT","link":"Carado_2__I-449-338-449-pro_2__Integriert"},{"typ":"Integriert","hersteller":"Carado","basis":"Carado","modell":"I 447","sitz":"7","serie":"AH/GT","link":"Carado_2__I-447_2__Integriert"},{"typ":"Integriert","hersteller":"Carado","basis":"Carado","modell":"Edition15 I","sitz":"7","serie":"AH/GT","link":"Carado_2__Edition15-I_2__Integriert"},{"typ":"Integriert","hersteller":"Dethleffs","basis":"Dethleffs","modell":"Globebus I1 I6","sitz":"7","serie":"AH/GT","link":"Dethleffs_2__Globebus-I1-I6_2__Integriert"},{"typ":"Integriert","hersteller":"Dethleffs","basis":"Dethleffs","modell":"Trend I","sitz":"7","serie":"AH/GT","link":"Dethleffs_2__Trend-I_2__Integriert"},{"typ":"Integriert","hersteller":"Etrusco","basis":"Etrusco","modell":"I Modell","sitz":"7","serie":"AH/GT","link":"Etrusco_2__I-Modell_2__Integriert"},{"typ":"Integriert","hersteller":"Eura Mobil","basis":"Eura Mobil","modell":"Forster","sitz":"7","serie":"AH/GT","link":"Eura-Mobil_2__Forster_2__Integriert"},{"typ":"Integriert","hersteller":"Eura Mobil","basis":"Eura Mobil","modell":"Integra","sitz":"7","serie":"AH/GT","link":"Eura-Mobil_2__Integra_2__Integriert"},{"typ":"Integriert","hersteller":"Eura Mobil","basis":"Eura Mobil","modell":"Integra Line","sitz":"7","serie":"AH/GT","link":"Eura-Mobil_2__Integra-Line_2__Integriert"},{"typ":"Kastenwagen","hersteller":"Dethleffs","basis":"VW Crafter","modell":"Globetrail Performance","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Dethleffs_2__Globetrail-Performance_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"ERIBA","basis":"VW Crafter","modell":"CAR 600 ACC","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"ERIBA_2__CAR-600-ACC_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Hobby","basis":"VW Crafter","modell":"Maxia Van","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Hobby_2__Maxia-Van_2__Kastenwagen"},{"typ":"Teilintegriert","hersteller":"Knaus","basis":"VW Crafter, MAN TGE","modell":"VAN TI Plus","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Knaus_2__VAN-TI-Plus_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Knaus","basis":"VW Crafter, MAN TGE","modell":"VAN WAVE","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Knaus_2__VAN-WAVE_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Knaus","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer, VW Crafter","modell":"LIVE WAVE","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Knaus_2__LIVE-WAVE_2__Teilintegriert"},{"typ":"nan","hersteller":"Knaus","basis":"VW Crafter, MAN TGE","modell":"BOXDRIVE / XL","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Knaus_2__BOXDRIVE-XL_2__nan"},{"typ":"Integriert","hersteller":"Benimar","basis":"Ford Transit","modell":"Yrteo","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Benimar_2__Yrteo_2__Integriert"},{"typ":"Teilintegriert","hersteller":"Benimar","basis":"Ford Transit","modell":"Tessoro T","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Benimar_2__Tessoro-T_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Bürstner","basis":"Ford Transit","modell":"Lineo T","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Buerstner_2__Lineo-T_2__Teilintegriert"},{"typ":"Alkoven","hersteller":"Bürstner","basis":"Fiat Ducato 250, Citroen Jumper,  Peugeot Boxer","modell":"Argos","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Buerstner_2__Argos_2__Alkoven"},{"typ":"Urban-Camper","hersteller":"Bürstner","basis":"Ford Transit","modell":"Copa","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Buerstner_2__Copa_2__Urban-Camper"},{"typ":"Alkoven","hersteller":"Challenger","basis":"Ford Transit","modell":"C194 Alkoven","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Challenger_2__C194-Alkoven_2__Alkoven"},{"typ":"Teilintegriert","hersteller":"Challenger","basis":"Ford Transit","modell":"Graphite ULTIMATE Arctic Connect","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Challenger_2__Graphite-ULTIMATE-Arctic-Connect_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Challenger","basis":"Ford Transit","modell":"Teilintegriert 384 380 287 328","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Challenger_2__Teilintegriert-384-380-287-328_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Challenger","basis":"Ford Transit","modell":"Teilintegriert S","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Challenger_2__Teilintegriert-S_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Dethleffs","basis":"Ford Transit","modell":"Globebus Go","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Dethleffs_2__Globebus-Go_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Dethleffs","basis":"Ford Transit","modell":"Just Go","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Dethleffs_2__Just-Go_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Dethleffs","basis":"Ford Transit","modell":"Yoka Go","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Dethleffs_2__Yoka-Go_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Etrusco","basis":"Ford Transit","modell":"T Modell","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Etrusco_2__T-Modell_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Etrusco","basis":"Ford Transit","modell":"V 5.9 DF V 6.6 SF","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Etrusco_2__V-5-9-DF-V-6-6-SF_2__Teilintegriert"},{"typ":"Urban-Camper","hersteller":"Dethleffs","basis":"Ford Custom","modell":"Globevan Camp One Two","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Dethleffs_2__Globevan-Camp-One-Two_2__Urban-Camper"},{"typ":"Urban-Camper","hersteller":"Dethleffs","basis":"Ford Custom","modell":"Globevan Trail One Two","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Dethleffs_2__Globevan-Trail-One-Two_2__Urban-Camper"},{"typ":"Urban-Camper","hersteller":"Ford","basis":"Ford Custom","modell":"Nugget Transit Custom","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Ford_2__Nugget-Transit-Custom_2__Urban-Camper"},{"typ":"Urban-Camper","hersteller":"Ford","basis":"Ford Custom","modell":"Transit Custom Vanestro Atlantic Titanium","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Ford_2__Transit-Custom-Vanestro-Atlantic-Titanium_2__Urban-Camper"},{"typ":"Urban-Camper","hersteller":"Ford","basis":"Ford Custom","modell":"Transit Custom Vanestro Pacific Titanium","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Ford_2__Transit-Custom-Vanestro-Pacific-Titanium_2__Urban-Camper"},{"typ":"Kastenwagen","hersteller":"Dreamer","basis":"Ford Custom","modell":"Select CAP COAST LAND","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Dreamer_2__Select-CAP-COAST-LAND_2__Kastenwagen"},{"typ":"Teilintegriert","hersteller":"Chausson","basis":"Ford Transit","modell":"Sport Line","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Chausson_2__Sport-Line_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Chausson","basis":"Ford Transit","modell":"Titanium ultimate","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Chausson_2__Titanium-ultimate_2__Teilintegriert"},{"typ":"Kastenwagen","hersteller":"GiottiLine","basis":"Ford Transit","modell":"GiottiVan 60FT","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"GiottiLine_2__GiottiVan-60FT_2__Kastenwagen"},{"typ":"nan","hersteller":"GiottiLine","basis":"Ford Transit","modell":"Siena T","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"GiottiLine_2__Siena-T_2__nan"},{"typ":"Kastenwagen","hersteller":"Dethleffs","basis":"Ford Transit","modell":"Globetrail Classic Advantage","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Dethleffs_2__Globetrail-Classic-Advantage_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Dreamer","basis":"Ford Transit","modell":"Fun D51","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Dreamer_2__Fun-D51_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Dreamer","basis":"Ford Transit","modell":"Select D51 Select","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Dreamer_2__Select-D51-Select_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Etrusco","basis":"Ford Transit","modell":"CV 600 DF 4x4","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Etrusco_2__CV-600-DF-4x4_2__Kastenwagen"},{"typ":"Teilintegriert","hersteller":"Pilote","basis":"Ford Transit","modell":"Atlas","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Pilote_2__Atlas_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Roller Team","basis":"Ford Transit","modell":"Ford Kronos TI","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Roller-Team_2__Ford-Kronos-TI_2__Teilintegriert"},{"typ":"Alkoven","hersteller":"Roller Team","basis":"Ford Transit","modell":"Ford Kronos Alkoven","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Roller-Team_2__Ford-Kronos-Alkoven_2__Alkoven"},{"typ":"Kastenwagen","hersteller":"Adria","basis":"MAN TGE","modell":"Twin Max","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Adria_2__Twin-Max_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"Bravia","basis":"MAN TGE","modell":"Swan 680","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Bravia_2__Swan-680_2__Kastenwagen"},{"typ":"Kastenwagen","hersteller":"LMC","basis":"Ford Transit","modell":"Innovan / Active","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"LMC_2__Innovan-Active_2__Kastenwagen"},{"typ":"Teilintegriert","hersteller":"McLouis","basis":"Ford Transit","modell":"MC4 Transit","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"McLouis_2__MC4-Transit_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Mooveo","basis":"Ford Transit","modell":"TEI Transit","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Mooveo_2__TEI-Transit_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"LMC","basis":"Ford Transit","modell":"Tourer Lift","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"LMC_2__Tourer-Lift_2__Teilintegriert"},{"typ":"Kastenwagen","hersteller":"Affinity","basis":"MAN TGE","modell":"Camper Van M DUO","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Affinity_2__Camper-Van-M-DUO_2__Kastenwagen"},{"typ":"Urban-Camper","hersteller":"Karmann","basis":"Ford Transit Custom","modell":"Duncan","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Karmann_2__Duncan_2__Urban-Camper"},{"typ":"Kastenwagen","hersteller":"Karmann","basis":"Ford Transit","modell":"Dexter 4x4","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Karmann_2__Dexter-4x4_2__Kastenwagen"},{"typ":"Teilintegriert","hersteller":"Knaus","basis":"VW T6","modell":"Tourer CUV","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Knaus_2__Tourer-CUV_2__Teilintegriert"},{"typ":"Teilintegriert","hersteller":"Knaus","basis":"VW T6","modell":"TOURER VAN","sitz":"9+10","serie":"PLKT/WPL/WMS/C/H/DPL/T","link":"Knaus_2__TOURER-VAN_2__Teilintegriert"},{"typ":"Kastenwagen","hersteller":"La Strada","basis":"Mercedes Sprinter W907","modell":"Nova / Regent","sitz":"auf anfrage","serie":"","link":"La-Strada_2__Nova-Regent_2__Kastenwagen"},{"typ":"Urban-Camper","hersteller":"Adria","basis":"Renault Trafic","modell":"Active","sitz":"auf anfrage","serie":"","link":"Adria_2__Active_2__Urban-Camper"},{"typ":"Urban-Camper","hersteller":"Mooveo","basis":"Renault Trafic","modell":"Campervan / XL","sitz":"auf anfrage","serie":"","link":"Mooveo_2__Campervan-XL_2__Urban-Camper"},{"typ":"Alkoven","hersteller":"Frankia","basis":"Renault Trafic","modell":"Yucon T","sitz":"auf anfrage","serie":"","link":"Frankia_2__Yucon-T_2__Alkoven"},{"typ":"Urban-Camper","hersteller":"Panama","basis":"Ford Transit, Tourneo Custom, Peugeot Traveller","modell":"P Peak / Urban","sitz":"auf anfrage","serie":"","link":"Panama_2__P-Peak-Urban_2__Urban-Camper"}];
    
    
    
    /* Hinweis:
    
       Ich habe hier im Chat aus PlatzgrÃ¼nden die RECORDS-Ausgabe gekÃ¼rzt dargestellt.
    
       In deiner echten Datei müssen alle Datensätze drin stehen.
    
       Wenn du willst, poste ich dir im nächsten Schritt die RECORDS komplett (283 Zeilen) – sag nur "poste RECORDS komplett".
    
    */
    
    
    
  const SEATS = [
    
      { id:"s1", code:"1", name:"Sitz 1", tooltip:"Standard-Sitz", img:"https://otomac.de/img/konfi/1.jpg" },
    
      { id:"s2", code:"2", name:"Sitz 2", tooltip:"Sitz mit Gurt", img:"https://otomac.de/img/konfi/2.jpg" },
    
      { id:"s3", code:"3", name:"Sitz 3", tooltip:"Captain Chair", img:"https://otomac.de/img/konfi/3.jpg" },
    
      { id:"s4", code:"4", name:"Sitz 4", tooltip:"Captain Chair mit Gurt", img:"https://otomac.de/img/konfi/4.jpg" },
    
      { id:"s5", code:"5", name:"Sitz 5", tooltip:"Komfort-Sitz", img:"https://otomac.de/img/konfi/5.jpg" },
    
      { id:"s6", code:"6", name:"Sitz 6", tooltip:"Komfort-Sitz mit Gurt", img:"https://otomac.de/img/konfi/6.jpg" },
    
      { id:"s7", code:"7", name:"Sitz 7", tooltip:"Drehkonsole / Armlehnen", img:"https://otomac.de/img/konfi/7.jpg" },
    
      { id:"s8", code:"8", name:"Sitz 8", tooltip:"Premium-Sitz", img:"https://otomac.de/img/konfi/8.jpg" },
    
      { id:"s9", code:"9", name:"Sitz 9", tooltip:"Fahrerhaus-Sitz", img:"https://otomac.de/img/konfi/9.jpg" },
    
      { id:"s10", code:"10", name:"Sitz 10", tooltip:"Fahrerhaus-Sitz (Komfort)", img:"https://otomac.de/img/konfi/10.jpg" }
    
    ];
    
  
  // Visuelle Darstellung für Serien (C, DPL, H, PLKT, WPL, WMS, T, etc.)
  // Bild-URLs sind Platzhalter – bitte bei Bedarf mit echten Pfaden ersetzen.
  const SERIES_META = {
  
    "C": {
      label: "Serie C",
      color: "#e3f2fd",
      img:   "https://otomac.de/img/serie/C.png"
    },
  
    "DPL": {
      label: "Serie DPL",
      color: "#f3e5f5",
      img:   "https://otomac.de/img/serie/DPL.png"
    },
  
    "H": {
      label: "Serie H",
      color: "#e8f5e9",
      img:   "https://otomac.de/img/serie/H.png"
    },
  
    "PLKT": {
      label: "Serie PLKT",
      color: "#fff8e1",
      img:   "https://otomac.de/img/serie/PLKT.png"
    },
  
    "WPL": {
      label: "Serie WPL",
      color: "#e1f5fe",
      img:   "https://otomac.de/img/serie/WPL.png"
    },
  
    "WMS": {
      label: "Serie WMS",
      color: "#ede7f6",
      img:   "https://otomac.de/img/serie/WMS.png"
    },
  
    "T": {
      label: "Serie T",
      color: "#f1f8e9",
      img:   "https://otomac.de/img/serie/T.png"
    }
  
  };
  
  function getSerieMeta(code){
  
    const c = String(code || "").trim();
  
    const meta = SERIES_META[c] || {};
  
    return {
  
      code: c,
  
      label: meta.label || `Serie ${c}`,
  
      desc: meta.desc  || "",
  
      color: meta.color || "#f5f5f5",
  
      img:   meta.img   || ""
  
    };
  
  }
    
    
    /* ======= LOGIK ======= */
    
    const els = {
    
      step1: container.querySelector("#step1"),
    
      step2: container.querySelector("#step2"),
    
      step3: container.querySelector("#step3"),
    
      step4: container.querySelector("#step4"),
    
    
    
      lbl1: container.querySelector("#lbl1"),
    
      lbl2: container.querySelector("#lbl2"),
    
      lbl3: container.querySelector("#lbl3"),
    
      lbl4: container.querySelector("#lbl4"),
    
      dot1: container.querySelector("#dot1"),
    
      dot2: container.querySelector("#dot2"),
    
      dot3: container.querySelector("#dot3"),
    
      dot4: container.querySelector("#dot4"),
    
      stepText: container.querySelector("#stepText"),
    
      bar: container.querySelector("#bar"),
    
    
    
      toStep2: container.querySelector("#toStep2"),
    
      backTo1: container.querySelector("#backTo1"),
    
      toStep3: container.querySelector("#toStep3"),
    
      backTo2: container.querySelector("#backTo2"),
    
      toStep4: container.querySelector("#toStep4"),
    
      backTo3: container.querySelector("#backTo3"),
    
      finish: container.querySelector("#finish"),
    
    
    
    
      her: container.querySelector("#hersteller"),
    
      basis: container.querySelector("#basisfahrzeug"),
    
      modell: container.querySelector("#modell"),
    
      modellCustom: container.querySelector("#modell_custom"),
    
      year: container.querySelector("#baujahr"),
    
    
    
      herAc: container.querySelector("#hersteller_ac"),
    
      basisAc: container.querySelector("#basis_ac"),
    
      modellAc: container.querySelector("#modell_ac"),
    
      herPanel: container.querySelector("#hersteller_panel"),
    
      basisPanel: container.querySelector("#basis_panel"),
    
      modellPanel: container.querySelector("#modell_panel"),
    
    
    
      reqHer: container.querySelector("#reqHer"),
    
      reqBasis: container.querySelector("#reqBasis"),
    
      reqModell: container.querySelector("#reqModell"),
    
    
    
      sumTyp: container.querySelector("#sum_typ"),
    
      sumHer: container.querySelector("#sum_her"),
    
      sumBasis: container.querySelector("#sum_basis"),
    
      sumModell: container.querySelector("#sum_modell"),
    
      sumYear: container.querySelector("#sum_year"),
    
      sumSitz: container.querySelector("#sum_sitz"),
    
      sumSerie: container.querySelector("#sum_serie"),
    
    
    
    seatGrid: container.querySelector("#seatGrid"),
    
      seatHint: container.querySelector("#seatHint"),
    
    serieHint: container.querySelector("#serieHint"),
  
    serieGrid: container.querySelector("#serieGrid"),
  
    serieEmptyFallback: container.querySelector("#serieEmptyFallback"),
  
    serieFallbackForm: container.querySelector("#serieFallbackForm"),
  
    fallbackImages: container.querySelector("#fallback_images"),
  
    fallbackImagesTrigger: container.querySelector("#fallback_images_trigger"),
  
    fallbackImagesInfo: container.querySelector("#fallback_images_info"),
    
    
    
      serie: container.querySelector("#serie"),
    
      serieAc: container.querySelector("#serie_ac"),
    
      seriePanel: container.querySelector("#serie_panel"),
    
      reqSerie: container.querySelector("#reqSerie"),
    
    };
    
    
    
    const state = {
    
      step: 1,
    
      autoStep3Armed: true,
    
      isLoadingSerie: false,
    
      vehicleKey: "",
    
      vehicleLabel: "",
    
      vehicleUrl: "",
    
      hersteller: "",
    
      basis: "",
    
      modell: "",
    
      customModell: "",
    
      year: "",
    
      seatId: "",
    
      serie: ""
    
    };
  
    const CACHE_KEY = "womosi_konfigurator_state";
    let _restoring = false;
  
    function saveState(){
      if(_restoring) return;
      try {
        sessionStorage.setItem(CACHE_KEY, JSON.stringify({
          step: state.step,
          vehicleKey: state.vehicleKey,
          vehicleLabel: state.vehicleLabel,
          vehicleUrl: state.vehicleUrl,
          hersteller: state.hersteller,
          basis: state.basis,
          modell: state.modell,
          customModell: state.customModell,
          year: state.year,
          seatId: state.seatId,
          serie: state.serie
        }));
      } catch(e) { /* storage full or unavailable */ }
    }
  
    function getSavedState(){
      try {
        const raw = sessionStorage.getItem(CACHE_KEY);
        return raw ? JSON.parse(raw) : null;
      } catch(e) { return null; }
    }
  
    function clearSavedState(){
      try { sessionStorage.removeItem(CACHE_KEY); } catch(e) { /* */ }
    }
    
    
    
    function updateProgressBar(){
    
      let progress = 0;
    
      
    
      // Step 1: Fahrzeugtyp (0-25%)
    
      if(state.vehicleKey) progress += 25;
    
      
    
      // Step 2: Details (25-50%) - Her alan 6.25%
    
      if(state.vehicleKey){
    
        const step2Fields = [
    
          state.hersteller || els.her.disabled,
    
          state.basis || els.basis.disabled,
    
          getFinalModel(),
    
          state.year
    
        ];
    
        const filledFields = step2Fields.filter(Boolean).length;
    
        progress += (filledFields / 4) * 25; // Her alan 6.25%
    
      }
    
      
    
      // Step 3: Sitz (50-75%)
    
      if(state.seatId) progress += 25;
    
      
    
      // Step 4: Serie (75-100%)
    
      if(state.serie) progress += 25;
    
      
    
      // Minimum ve maksimum kontrolü
    
      progress = Math.min(100, Math.max(0, progress));
    
      els.bar.style.width = progress + "%";
    
    }
    
    
    
    function setStep(step){
    
      state.step = step;
    
      els.step1.classList.toggle("hidden", step !== 1);
    
      els.step2.classList.toggle("hidden", step !== 2);
    
      els.step3.classList.toggle("hidden", step !== 3);
    
      els.step4.classList.toggle("hidden", step !== 4);
    
    
    
      if(els.stepText) els.stepText.textContent = `Step ${step}/4`;
    
      
    
      updateProgressBar();
    
    
    
      els.lbl1.classList.toggle("active", step === 1);
    
      els.lbl2.classList.toggle("active", step === 2);
    
      els.lbl3.classList.toggle("active", step === 3);
    
      els.lbl4.classList.toggle("active", step === 4);
    
      els.dot1.classList.toggle("active", step === 1);
    
      els.dot2.classList.toggle("active", step === 2);
    
      els.dot3.classList.toggle("active", step === 3);
    
      els.dot4.classList.toggle("active", step === 4);
    
    
    
      saveState();
  
      if(!_restoring){
        requestAnimationFrame(()=>{
          const rect = container.getBoundingClientRect();
          const top = rect.top + window.pageYOffset - 10;
          if(rect.top < 0 || rect.top > window.innerHeight * 0.5){
            window.scrollTo({ top: Math.max(0, top), behavior: "smooth" });
          }
        });
      }
    
    }
    
    
    
    const uniq = (arr) => Array.from(new Set(arr)).filter(Boolean);
    
    
    
    function setSelectOptions(sel, values){
    
      sel.innerHTML = "";
    
      const opt0 = document.createElement("option");
    
      opt0.value = "";
    
      opt0.textContent = "Bitte wählen…";
    
      sel.appendChild(opt0);
    
      values.forEach(v=>{
    
        const o = document.createElement("option");
    
        o.value = v;
    
        o.textContent = v;
    
        sel.appendChild(o);
    
      });
    
    }
    
    
    
    function setRequiredState(sel, reqEl){
    
      const hasValues = sel.options.length > 1;
    
      if(!hasValues){
    
        sel.disabled = true;
    
        reqEl.classList.add("hidden");
    
      }else{
    
        sel.disabled = false;
    
        reqEl.classList.remove("hidden");
    
      }
    
    }
    
    
    
    function recordsForVehicle(){
    
      return RECORDS.filter(r => r.typ === state.vehicleKey);
    
    }
    
    
    
    function getFinalModel(){
    
      const m = (state.modell || "").trim();
    
      const c = (state.customModell || "").trim();
    
      return m || c;
    
    }
    
    
    
    /* ✓ Link oluşturma fonksiyonu */
    
    function formatLinkPart(str){
    
      if(!str) return "";
    
      let s = String(str).trim();
    
      s = s.replace(/ü/g, 'ue').replace(/ö/g, 'oe').replace(/ä/g, 'ae');
    
      s = s.replace(/ÃƒÅ“/g, 'Ue').replace(/Ãƒâ€“/g, 'Oe').replace(/Ä/g, 'Ae');
    
      s = s.replace(/ß/g, 'ss').replace(/\s+/g, '-');
    
      s = s.replace(/[\/\+,\\.!?]/g, '-');
    
      s = s.replace(/[^a-zA-Z0-9-]/g, '');
    
      s = s.replace(/-+/g, '-').replace(/^-|-$/g, '');
    
      return s;
    
    }
    
    
    
    function generateWomosiLink(){
  
      // Link: Hersteller__Basisfahrzeug__Modell__Serie__Fahrzeugtyp
      // customModell: Hersteller__Basisfahrzeug__Serie__Fahrzeugtyp
  
      if(!state.serie || !state.vehicleKey || !state.hersteller) return null;
  
      const h = formatLinkPart(state.hersteller);
      const b = formatLinkPart(state.basis || "");
      const m = (!state.customModell && state.modell) ? formatLinkPart(state.modell) : "";
      const s = formatLinkPart(state.serie);
      const t = formatLinkPart(state.vehicleKey);
  
      if(!h || !s || !t) return null;
  
      let path = h;
      if(b) path += "__" + b;
      if(m) path += "__" + m;
      path += "__" + s + "__" + t;
  
      return `https://womosi.de/${path}`;
  
    }
    
    
    
    /* ✓ Basisfahrzeug: splitte mehrere Werte pro Zelle in einzelne Dropdown-Werte */
    
    function parseBasisList(basisStr){
    
      const s = (basisStr ?? "").toString().trim();
    
      if(!s || s.toLowerCase() === "nan") return [];
    
      return s.split(/[,;]+/).map(x => x.trim()).filter(Boolean);
    
    }
    
    function recordHasBasis(rec, chosenBasis){
    
      if(!chosenBasis) return true;
    
      const list = parseBasisList(rec.basis);
    
      return list.includes(chosenBasis);
    
    }
    
    
    
    /* ✓ Sitze aus Excel: "6+9+10", "2,3", "7" ... -> parse */
    
    function parseSeatCodes(sitzStr){
    
      const s = (sitzStr ?? "").toString().trim();
    
      if(!s || s.toLowerCase() === "nan") return [];
    
      return s
    
        .split(/[^0-9]+/)
    
        .map(x => x.trim())
    
        .filter(Boolean)
    
        .map(x => parseInt(x,10))
    
        .filter(n => Number.isFinite(n) && n>=1 && n<=10);
    
    }
    
    function matchingRecordsForSeats(){
    
      let recs = recordsForVehicle().filter(r => !state.hersteller || r.hersteller === state.hersteller);
    
      if(!els.basis.disabled && state.basis) recs = recs.filter(r => recordHasBasis(r, state.basis));
    
      if(state.modell) recs = recs.filter(r => r.modell === state.modell);
    
      if(recs.length === 0) recs = recordsForVehicle();
    
      return recs;
    
    }
    
    function seatCodesForCurrentSelection(){
    
      const recs = matchingRecordsForSeats();
    
      const codes = recs.flatMap(r => parseSeatCodes(r.sitz));
    
      return uniq(codes.map(String));
    
    }
    
    
    
    function validateStep2(){
    
      if(!state.vehicleKey) return {ok:false, msg:"Bitte zuerst einen Fahrzeugtyp auswählen."};
    
      if(!els.her.disabled && !state.hersteller) return {ok:false, msg:"Bitte Hersteller auswählen."};
    
      if(!els.basis.disabled && !state.basis) return {ok:false, msg:"Bitte Basisfahrzeug auswählen."};
    
      const fm = getFinalModel();
    
      if((!els.modell.disabled || !els.modellCustom.disabled) && !fm) return {ok:false, msg:"Bitte Modell auswählen oder eintragen."};
    
      const y = String(state.year||"").trim();
    
      const yn = y ? Number(y) : NaN;
    
      if(!y || !Number.isInteger(yn) || yn < 1900 || yn > 2100) return {ok:false, msg:"Bitte gültiges Baujahr (1900–2100) eingeben."};
    
      return {ok:true, msg:""};
    
    }
    
    
    
    function updateSummary(){
    
      if(els.sumTyp) els.sumTyp.textContent = state.vehicleLabel || "–";
    
      if(els.sumHer) els.sumHer.textContent = state.hersteller || "–";
    
      if(els.sumBasis) els.sumBasis.textContent = state.basis || "–";
    
      if(els.sumModell) els.sumModell.textContent = getFinalModel() || "–";
    
      if(els.sumYear) els.sumYear.textContent = state.year || "–";
    
      const seatObj = SEATS.find(s => s.id === state.seatId);
    
      if(els.sumSitz) els.sumSitz.textContent = seatObj ? seatObj.name : "–";
    
      if(els.sumSerie) els.sumSerie.textContent = state.serie || "–";
    
    
    
      const ok = validateStep2().ok;
    
      if(els.toStep3) els.toStep3.disabled = !ok;
    
      
    
      if(els.toStep4) {
    
        els.toStep4.disabled = !state.seatId;
    
      }
    
      
    
      if(els.finish) {
    
        els.finish.disabled = !state.serie;
    
      }
    
    
    
      if(state.step === 2){
    
        const v = validateStep2();
    
        if(v.ok && state.autoStep3Armed){
    
          state.autoStep3Armed = false;
    
          setStep(3);
    
          renderSeats();
    
        }
    
        if(!v.ok) state.autoStep3Armed = true;
    
      }
    
      
    
      if(state.step === 3 && state.seatId && !state.isLoadingSerie){
    
        state.isLoadingSerie = true;
    
        loadSerie();
    
        state.isLoadingSerie = false;
    
      }
    
      
    
      // Progress bar'ı güncelle
    
      updateProgressBar();
  
      saveState();
    
    }
    
    
    
    function isMobile(){ return window.innerWidth <= 768; }
  
    function getOrCreateOverlay(){
      let ov = document.querySelector(".womosi-konfigurator-nf > .ac-overlay") || document.querySelector("body > .ac-overlay");
      if(!ov){
        ov = document.createElement("div");
        ov.className = "ac-overlay";
      }
      if(ov.parentNode !== document.body){
        document.body.appendChild(ov);
      }
      return ov;
    }
  
    function bindAutocomplete(inputEl, panelEl, selectEl, onChange){
  
      inputEl.readOnly = true;
  
      let panelHeader = null;
      let panelSearchInput = null;
      let listWrap = null;
      let _vpHandler = null;
  
      const fieldLabel = (inputEl.closest(".field") || {}).querySelector && inputEl.closest(".field").querySelector("label");
      const labelText = fieldLabel ? fieldLabel.textContent.replace(/\*$/, "").trim() : "";
  
      function buildPanelUI(){
        if(panelHeader) return;
  
        panelHeader = document.createElement("div");
        panelHeader.className = "ac-panel-header";
        const backBtn = document.createElement("button");
        backBtn.type = "button";
        backBtn.className = "ac-panel-back";
        backBtn.innerHTML = "\u2190";
        backBtn.addEventListener("click", (e)=>{ e.preventDefault(); e.stopPropagation(); close(); });
        const title = document.createElement("span");
        title.className = "ac-panel-title";
        title.textContent = labelText || "Auswahl";
        const closeBtn = document.createElement("button");
        closeBtn.type = "button";
        closeBtn.className = "ac-panel-close";
        closeBtn.innerHTML = "\u00d7";
        closeBtn.addEventListener("click", (e)=>{ e.preventDefault(); e.stopPropagation(); close(); });
        panelHeader.appendChild(backBtn);
        panelHeader.appendChild(title);
        panelHeader.appendChild(closeBtn);
  
        panelSearchInput = document.createElement("input");
        panelSearchInput.type = "text";
        panelSearchInput.className = "ac-panel-search";
        panelSearchInput.placeholder = (labelText || "Wert") + " suchen\u2026";
        panelSearchInput.addEventListener("input", ()=>{ render(panelSearchInput.value); });
  
        listWrap = document.createElement("div");
        listWrap.className = "ac-panel-list";
      }
  
      function _syncPanelHeight(){
        if(panelEl.style.display === "none") return;
        const vv = window.visualViewport;
        if(vv){
          panelEl.style.height = vv.height + "px";
          panelEl.style.top = vv.offsetTop + "px";
        }
      }
  
      function close(){
        panelEl.style.display = "none";
        panelEl.style.height = "";
        panelEl.style.top = "";
        const ov = document.querySelector("body > .ac-overlay");
        if(ov) ov.classList.remove("active");
        document.body.style.overflow = "";
        if(_vpHandler && window.visualViewport){
          window.visualViewport.removeEventListener("resize", _vpHandler);
          window.visualViewport.removeEventListener("scroll", _vpHandler);
          _vpHandler = null;
        }
      }
  
      function open(){
        inputEl.blur();
        buildPanelUI();
  
        panelEl.innerHTML = "";
        panelEl.appendChild(panelHeader);
        panelEl.appendChild(panelSearchInput);
        panelEl.appendChild(listWrap);
        listWrap.innerHTML = "";
  
        // Panel'i body'ye taşı - stacking context sorunlarını önler
        if(panelEl.parentNode !== document.body){
          document.body.appendChild(panelEl);
        }
  
        panelEl.style.display = "flex";
  
        const ov = getOrCreateOverlay();
        ov.classList.add("active");
        ov.onclick = ()=> close();
        document.body.style.overflow = "hidden";
  
        if(panelSearchInput) panelSearchInput.value = "";
  
        _syncPanelHeight();
        if(window.visualViewport){
          _vpHandler = _syncPanelHeight;
          window.visualViewport.addEventListener("resize", _vpHandler);
          window.visualViewport.addEventListener("scroll", _vpHandler);
        }
  
        setTimeout(()=>{
          if(panelSearchInput) panelSearchInput.focus();
          const sel = listWrap && listWrap.querySelector(".is-selected");
          if(sel) sel.scrollIntoView({ block: "center", behavior: "instant" });
        }, 250);
      }
    
    
    
      function clearPanelItems(){
        if(listWrap) listWrap.innerHTML = "";
      }
  
      function render(filterText){
  
        const opts = Array.from(selectEl.options).slice(1).map(o=>o.value);
  
        const q = (filterText||"").toLowerCase().trim();
  
        const list = q ? opts.filter(v => v.toLowerCase().includes(q)) : opts;
  
        clearPanelItems();
  
        if(!listWrap) return;
  
        if(selectEl.disabled || opts.length === 0){
          const empty = document.createElement("div");
          empty.className = "ac-empty";
          empty.innerHTML = "Keine Eintr\u00e4ge verf\u00fcgbar";
          listWrap.appendChild(empty);
          return;
        }
  
        if(list.length === 0){
          const empty = document.createElement("div");
          empty.className = "ac-empty";
          empty.innerHTML = "Keine Treffer f\u00fcr \u201e" + (filterText||"").substring(0,30) + "\u201c";
          listWrap.appendChild(empty);
          return;
        }
  
        const currentVal = selectEl.value || "";
  
        list.slice(0,120).forEach(v=>{
  
          const div = document.createElement("div");
  
          div.className = "ac-item" + (v === currentVal ? " is-selected" : "");
  
          div.textContent = v;
  
          div.setAttribute("role", "option");
  
          div.setAttribute("tabindex", "0");
  
          div.addEventListener("click", (e)=>{
  
            e.preventDefault();
  
            e.stopPropagation();
  
            selectEl.value = v;
  
            inputEl.value = v;
  
            close();
  
            onChange();
  
            updateClearVisibility();
  
          });
  
          div.addEventListener("keydown", (e)=>{
  
            if(e.key === "Enter" || e.key === " "){
  
              e.preventDefault();
  
              selectEl.value = v;
  
              inputEl.value = v;
  
              close();
  
              onChange();
  
              updateClearVisibility();
  
            }
  
          });
  
          listWrap.appendChild(div);
  
        });
  
      }
  
      // Clear-Button (X) im Autocomplete-Feld
      const wrap = inputEl.closest(".ac-wrap");
      let clearBtn = null;
      function updateClearVisibility(){
        if(!clearBtn) return;
        const hasVal = (inputEl.value || "").trim().length > 0;
        clearBtn.classList.toggle("visible", hasVal);
      }
      if(wrap){
        clearBtn = wrap.querySelector(".ac-clear");
        if(!clearBtn){
          clearBtn = document.createElement("button");
          clearBtn.type = "button";
          clearBtn.className = "ac-clear";
          clearBtn.innerHTML = "×";
          wrap.appendChild(clearBtn);
          clearBtn.addEventListener("click", (e)=>{
            e.preventDefault();
            e.stopPropagation();
            inputEl.value = "";
            if(selectEl) selectEl.value = "";
            close();
            onChange();
            updateClearVisibility();
          });
        }
        inputEl.addEventListener("input", updateClearVisibility);
        inputEl.addEventListener("focus", updateClearVisibility);
        updateClearVisibility();
      }
  
      let _justOpened = false;
  
      inputEl.addEventListener("click", (e)=>{
        e.preventDefault();
        e.stopPropagation();
        _justOpened = true;
        open();
        render("");
        setTimeout(()=>{ _justOpened = false; }, 300);
      });
  
      inputEl.addEventListener("focus", (e)=>{
        e.target.blur();
      });
  
      inputEl.addEventListener("keydown", (e)=>{
        if(e.key === "Escape") close();
      });
    
    
    
      document.addEventListener("click", (e)=>{
    
        if(_justOpened) return;
    
        if(panelEl.style.display === "flex" && panelEl.contains(e.target)) return;
    
        if(!container.contains(e.target)) return;
    
        if(!e.target.closest(".ac-wrap")) close();
    
      });
    
      
    
      document.addEventListener("keydown", (e)=>{
    
        if(e.key === "Escape" && panelEl.style.display !== "none") close();
    
      });
    
    
    
      selectEl.addEventListener("change", ()=>{
    
        inputEl.value = selectEl.value || "";
    
        close();
  
        onChange();
  
        updateClearVisibility();
    
      });
    
    
    
      return {
    
        refresh(){
    
          inputEl.value = selectEl.value || "";
    
          render(inputEl.value);
    
        }
    
      };
    
    }
    
    
    
    /* ===== Dropdown Loading ===== */
    
    function loadHersteller(){
    
      const recs = recordsForVehicle();
    
      const hersteller = uniq(recs.map(r=>r.hersteller)).sort((a,b)=>a.localeCompare(b,"de"));
    
      setSelectOptions(els.her, hersteller);
    
      setRequiredState(els.her, els.reqHer);
    
    
    
      state.hersteller = "";
    
      state.basis = "";
    
      state.modell = "";
    
      state.customModell = "";
    
      els.modellCustom.value = "";
    
      state.year = "";
    
      state.seatId = "";
    
    
    
      setSelectOptions(els.basis, []);
    
      setSelectOptions(els.modell, []);
    
      setRequiredState(els.basis, els.reqBasis);
    
      setRequiredState(els.modell, els.reqModell);
    
    
    
      els.modellCustom.disabled = true;
    
    
    
      if(typeof acHer !== 'undefined') acHer.refresh();
    
      if(typeof acBasis !== 'undefined') acBasis.refresh();
    
      if(typeof acModell !== 'undefined') acModell.refresh();
    
      updateSummary();
    
    }
    
    
    
    /* ✓ Basis-Dropdown zeigt einzelne Basisfahrzeuge (aus split) */
    
    function loadBasis(){
    
      const recs = recordsForVehicle().filter(r => !state.hersteller || r.hersteller === state.hersteller);
    
    
    
      const basis = uniq(
    
        recs.flatMap(r => parseBasisList(r.basis))
    
      ).sort((a,b)=>a.localeCompare(b,"de"));
    
    
    
      setSelectOptions(els.basis, basis);
    
      setRequiredState(els.basis, els.reqBasis);
    
    
    
      state.basis = "";
    
      els.basis.value = "";
    
      if(typeof acBasis !== 'undefined') acBasis.refresh();
    
    
    
      if (els.basis.disabled) {
    
        loadModel();
    
        return;
    
      }
    
    
    
      setSelectOptions(els.modell, []);
    
      setRequiredState(els.modell, els.reqModell);
    
    
    
      els.modellCustom.value = "";
    
      state.modell = "";
    
      state.customModell = "";
    
    
    
      els.modellCustom.disabled = !state.hersteller;
    
    
    
      if(typeof acModell !== 'undefined') acModell.refresh();
    
      updateSummary();
    
    }
    
    
    
    /* ✓ Modell-Filter berücksichtigt Basis auch bei split */
    
    function loadModel(){
    
      const recs = recordsForVehicle()
    
        .filter(r => !state.hersteller || r.hersteller === state.hersteller)
    
        .filter(r => recordHasBasis(r, state.basis));
    
    
    
      const modell = uniq(recs.map(r=>r.modell)).sort((a,b)=>a.localeCompare(b,"de"));
    
      setSelectOptions(els.modell, modell);
    
      setRequiredState(els.modell, els.reqModell);
    
    
    
      state.modell = "";
    
      els.modellCustom.value = "";
    
      state.customModell = "";
    
    
    
      els.modellCustom.disabled = !state.hersteller;
    
    
    
      if(typeof acModell !== 'undefined') acModell.refresh();
    
      updateSummary();
    
    }
    
    
    
    /* ✓ Sitze anhand Excel-Spalte Sitz filtern */
    
    function renderSeats(){
    
      const allowed = new Set(seatCodesForCurrentSelection().map(String));
    
      const list = allowed.size ? SEATS.filter(s => allowed.has(String(s.code))) : SEATS.slice();
    
    
    
      els.seatGrid.innerHTML = "";
    
      els.seatHint.textContent = allowed.size
    
        ? `Wähle deinen Sitz (verfügbar: ${Array.from(allowed).sort((a,b)=>Number(a)-Number(b)).join(", ")}).`
    
        : "Wähle deinen Sitz.";
    
    
    
      list.forEach(s=>{
    
        const a = document.createElement("a");
    
        a.href = "#";
    
        a.className = "tile-link" + (state.seatId === s.id ? " is-active" : "");
    
        a.innerHTML = `
    
          <div class="tile" tabindex="0" data-tooltip="${s.tooltip || ""}">
    
            <div class="imgWrap">
    
              <img src="${s.img}" alt="${s.name}">
    
            </div>
    
            <div class="name">${s.name}</div>
    
          </div>
    
        `;
    
        a.addEventListener("click",(e)=>{
    
          e.preventDefault();
    
          state.seatId = s.id;
    
          container.querySelectorAll("#seatGrid .tile-link").forEach(x=>x.classList.remove("is-active"));
    
          a.classList.add("is-active");
    
          updateSummary();
    
          // Otomatik olarak Step 4'e geç ve serileri yükle
    
          if(state.step === 3){
    
            setStep(4);
    
            loadSerie();
    
          }
    
        });
    
        els.seatGrid.appendChild(a);
    
      });
    
    }
    
    
    
    /* ✓ Serie-Dropdown basierend auf aktueller Auswahl */
    
    function loadSerie(){
    
    if(!els.serie) return;
    
      
    
    const recs = matchingRecordsForSeats();
    
    const seatObj = SEATS.find(s => s.id === state.seatId);
    
    if(!seatObj) {
      setSelectOptions(els.serie, []);
      setRequiredState(els.serie, els.reqSerie);
      state.serie = "";
      if(typeof acSerie !== 'undefined') acSerie.refresh();
      if(els.serieGrid) els.serieGrid.innerHTML = "";
      if(els.serieEmptyFallback) els.serieEmptyFallback.classList.add("hidden");
      if(els.serieGrid) els.serieGrid.classList.remove("hidden");
      if(els.serieHint) els.serieHint.classList.remove("hidden");
      return;
    }
    
      
    
      // Filtere nach Sitz-Code
    
      const seatCodes = parseSeatCodes(seatObj.code);
    
    const filteredRecs = recs.filter(r => {
    
        const rSeats = parseSeatCodes(r.sitz);
    
        return seatCodes.some(sc => rSeats.includes(sc));
    
      });
    
      
    
      // Extrahiere Serie-Werte (split bei "/" für multiple Werte)
    
    const serieValues = uniq(
    
        filteredRecs
    
          .flatMap(r => {
    
            const s = (r.serie || "").toString().trim();
    
            if(!s || s.toLowerCase() === "nan") return [];
    
            return s.split("/").map(x => x.trim()).filter(Boolean);
    
          })
    
    ).sort((a,b)=>a.localeCompare(b,"de"));
  
    
  
    // Kein Serie-Ergebnis: Fallback (Maßanfertigung) anzeigen
    if(serieValues.length === 0){
      if(els.serieEmptyFallback) els.serieEmptyFallback.classList.remove("hidden");
      if(els.serieGrid) els.serieGrid.classList.add("hidden");
      if(els.serieHint) els.serieHint.classList.add("hidden");
      if(els.finish) els.finish.disabled = true;
      if(els.serie) setSelectOptions(els.serie, []);
      if(els.serie && els.reqSerie) setRequiredState(els.serie, els.reqSerie);
      return;
    }
    if(els.serieEmptyFallback) els.serieEmptyFallback.classList.add("hidden");
    if(els.serieGrid) els.serieGrid.classList.remove("hidden");
    if(els.serieHint) els.serieHint.classList.remove("hidden");
  
    // Dropdown'ı arka planda güncel tut (Logik & Fallback)
    setSelectOptions(els.serie, serieValues);
    setRequiredState(els.serie, els.reqSerie);
    state.serie = "";
    els.serie.value = "";
    if(typeof acSerie !== 'undefined') acSerie.refresh();
  
    // Visuelle Serie-Kacheln aufbauen
    if(els.serieGrid){
      els.serieGrid.innerHTML = "";
  
      serieValues.forEach(code => {
  
        const meta = getSerieMeta(code);
  
        const a = document.createElement("a");
  
        a.href = "#";
  
        a.className = "tile-link serie-tile-link" + (state.serie === code ? " is-active" : "");
  
        a.innerHTML = `
          <div class="tile serie-tile" data-code="${meta.code}">
            ${meta.img ? `
              <div class="serie-imgWrap">
                <img src="${meta.img}" alt="${meta.label}">
              </div>
            ` : `
              <div class="serie-badge" style="background:${meta.color};">
                <span class="serie-code">${meta.code}</span>
              </div>
            `}
            <div class="name">${meta.label}</div>
            ${meta.desc ? `<div class="serie-desc">${meta.desc}</div>` : ""}
          </div>
        `;
  
        a.addEventListener("click",(e)=>{
  
          e.preventDefault();
  
          state.serie = meta.code;
  
          // Underlying Select + Input aktualisieren
          els.serie.value = meta.code;
  
          if(els.serieAc) els.serieAc.value = meta.code;
  
          // Active-Status updaten
          els.serieGrid.querySelectorAll(".serie-tile-link").forEach(x=>x.classList.remove("is-active"));
  
          a.classList.add("is-active");
  
          updateSummary();
  
          // Automatische Weiterleitung wie im Autocomplete
          if(state.serie && state.hersteller && getFinalModel() && state.vehicleKey && !state.customModell){
  
            const womosiLink = generateWomosiLink();
  
            if(womosiLink){
  
              setTimeout(() => {
  
                window.location.href = womosiLink;
  
              }, 300);
  
            }
  
          }
  
          if(els.finish){
  
            els.finish.disabled = !state.serie;
  
          }
  
        });
  
        els.serieGrid.appendChild(a);
  
      });
  
    }
  
    // Nur Finish-Button Zustand nachziehen (für Fälle ohne Klick)
  
    if(els.finish) {
  
      els.finish.disabled = !state.serie;
  
    }
    
    }
    
    
    
    /* ---------- bindings ---------- */
    
    const acHer = bindAutocomplete(els.herAc, els.herPanel, els.her, ()=>{
    
      state.hersteller = els.her.value || "";
    
      loadBasis();
    
    });
    
    const acBasis = bindAutocomplete(els.basisAc, els.basisPanel, els.basis, ()=>{
    
      state.basis = els.basis.value || "";
    
      loadModel();
    
    });
    
    const acModell = bindAutocomplete(els.modellAc, els.modellPanel, els.modell, ()=>{
    
      state.modell = els.modell.value || "";
    
      if(state.modell){
    
        els.modellCustom.value = "";
    
        state.customModell = "";
    
        els.modellCustom.disabled = true;
    
      }else{
    
        els.modellCustom.disabled = !state.hersteller;
    
      }
    
      updateSummary();
    
    });
    
    
    
    els.modellCustom.addEventListener("blur", ()=>{
    
      if(!els.modellCustom.value.trim() && !state.modell){
    
        els.modellCustom.disabled = !state.hersteller;
    
      }
    
    });
    
    
    
    const acSerie = bindAutocomplete(els.serieAc, els.seriePanel, els.serie, ()=>{
    
      state.serie = els.serie.value || "";
    
      updateSummary();
    
      
    
      // Serie seçildikten sonra otomatik olarak linke git (custom modell yoksa)
    
      if(state.serie && state.hersteller && getFinalModel() && state.vehicleKey && !state.customModell){
    
        const womosiLink = generateWomosiLink();
    
        if(womosiLink){
    
          // Kısa bir gecikme ile yönlendir (kullanıcı seçimi görebilsin)
    
          setTimeout(() => {
    
            window.location.href = womosiLink;
    
          }, 300);
    
        }
    
      }
    
    });
    
    
    
    els.modellCustom.addEventListener("input", ()=>{
    
      state.customModell = (els.modellCustom.value || "").trim();
    
      if(state.customModell){
    
        els.modell.value = "";
    
        state.modell = "";
    
        if(typeof acModell !== 'undefined') acModell.refresh();
    
      }
    
      updateSummary();
    
    });
    
    
    
    els.year.addEventListener("input", ()=>{
    
      els.year.value = els.year.value.replace(/\D+/g,"");
    
      state.year = els.year.value;
    
      updateSummary();
    
    });
    
    
    
    /* step buttons */
    
    const toStep2Btn = container.querySelector("#toStep2");
    
    if(toStep2Btn) toStep2Btn.addEventListener("click", (e)=> {
    
      e.preventDefault();
    
      if(state.vehicleKey) setStep(2);
    
    });
    
    
    
    const backTo1Btn = container.querySelector("#backTo1");
    
    if(backTo1Btn) backTo1Btn.addEventListener("click", (e)=> {
    
      e.preventDefault();
    
      setStep(1);
    
    });
    
    
    
    const toStep3Btn = container.querySelector("#toStep3");
    
    if(toStep3Btn) toStep3Btn.addEventListener("click", (e)=>{
    
      e.preventDefault();
    
      const v = validateStep2();
    
      if(!v.ok) {
    
        alert(v.msg);
    
        return;
    
      }
    
      setStep(3);
    
      renderSeats();
    
    });
    
    
    
    const backTo2Btn = container.querySelector("#backTo2");
    
    if(backTo2Btn) backTo2Btn.addEventListener("click", (e)=>{
    
      e.preventDefault();
    
      state.autoStep3Armed = true;
    
      setStep(2);
    
    });
    
    
    
    const toStep4Btn = container.querySelector("#toStep4");
    
    if(toStep4Btn) toStep4Btn.addEventListener("click", (e)=>{
    
      e.preventDefault();
    
      if(!state.seatId){
    
        alert("Bitte wähle zuerst einen Sitz aus.");
    
        return;
    
      }
    
      setStep(4);
    
      loadSerie();
    
    });
    
    
    
    const backTo3Btn = container.querySelector("#backTo3");
    
    if(backTo3Btn) backTo3Btn.addEventListener("click", (e)=>{
    
      e.preventDefault();
    
      setStep(3);
    
    });
    
    
    
    const finishBtn = container.querySelector("#finish");
    
    if(finishBtn) finishBtn.addEventListener("click", (e)=>{
    
      e.preventDefault();
    
      if(!state.serie){
    
        alert("Bitte wähle zuerst eine Serie aus.");
    
        return;
    
      }
    
      
    
      // Womosi link oluştur
    
      const womosiLink = generateWomosiLink();
    
      
    
      if(womosiLink){
    
        // Womosi linkine yönlendir
    
        window.location.href = womosiLink;
    
      } else {
    
        // Fallback: Eski yöntem (custom modell yoksa)
    
        const base = state.vehicleUrl || "#";
    
        const params = new URLSearchParams();
    
        if(state.vehicleLabel) params.set("typ", state.vehicleLabel);
    
        if(state.hersteller) params.set("hersteller", state.hersteller);
    
        if(state.basis) params.set("basis", state.basis);
    
        // Sadece seçilen modell'i ekle, custom modell'i ekleme
    
        if(state.modell && !state.customModell) params.set("modell", state.modell);
    
        if(state.year) params.set("baujahr", state.year);
    
        const seatObj = SEATS.find(s => s.id === state.seatId);
    
        if(seatObj) params.set("sitz", seatObj.code);
    
        if(state.serie) params.set("serie", state.serie);
    
        const url = base + (base.includes("?") ? "&" : "?") + params.toString();
    
        alert("Weiterleitung (Demo):\n" + url);
    
        // window.location.href = url;
    
      }
    
    });
    
    
    
    /* Vehicle click */
    
    const vehicleGrid = container.querySelector("#vehicleGrid");
    
    if(vehicleGrid) {
    
      vehicleGrid.addEventListener("click", (e)=>{
    
        let a = e.target;
    
        // Traverse up the DOM tree to find the tile-link
        while(a && a !== vehicleGrid) {
          if(a.tagName === "A" && a.classList.contains("tile-link") && a.hasAttribute("data-vehicle")) {
            break;
          }
          a = a.parentElement;
        }
    
        if(!a || a === vehicleGrid) return;
    
        e.preventDefault();
    
        e.stopPropagation();
    
    
    
        container.querySelectorAll("#vehicleGrid .tile-link").forEach(x=>x.classList.remove("is-active"));
    
        a.classList.add("is-active");
    
    
    
        state.vehicleKey = a.dataset.vehicle || "";
    
        state.vehicleLabel = a.dataset.label || "";
    
        state.vehicleUrl = a.dataset.url || "";
    
    
    
        const toStep2Btn = container.querySelector("#toStep2");
    
        if(toStep2Btn) toStep2Btn.disabled = !state.vehicleKey;
    
        const dot1 = container.querySelector("#dot1");
    
        if(dot1) dot1.textContent = state.vehicleKey ? "✓" : "1";
    
    
    
        state.autoStep3Armed = true;
    
        loadHersteller();
    
        updateSummary();
    
        setStep(2);
    
      });
    
    }
    
    
    
    /* Reset */
    
    function resetAll(){
    
      state.step = 1;
    
      state.autoStep3Armed = true;
    
      state.vehicleKey = "";
    
      state.vehicleLabel = "";
    
      state.vehicleUrl = "";
    
      state.hersteller = "";
    
      state.basis = "";
    
      state.modell = "";
    
      state.customModell = "";
    
      state.year = "";
    
      state.seatId = "";
    
      state.serie = "";
    
    
    
      container.querySelectorAll("#vehicleGrid .tile-link").forEach(x=>x.classList.remove("is-active"));
    
      container.querySelectorAll("#seatGrid .tile-link").forEach(x=>x.classList.remove("is-active"));
    
    
    
      els.her.value = ""; els.basis.value = ""; els.modell.value = "";
    
      els.herAc.value = ""; els.basisAc.value = ""; els.modellAc.value = "";
    
      els.modellCustom.value = "";
    
      els.year.value = "";
    
    if(els.serie) els.serie.value = "";
  
    if(els.serieAc) els.serieAc.value = "";
  
    if(els.serieFallbackForm) els.serieFallbackForm.reset();
  
    if(els.serieEmptyFallback) els.serieEmptyFallback.classList.add("hidden");
    
    
    
      setSelectOptions(els.her, []);
    
      setSelectOptions(els.basis, []);
    
      setSelectOptions(els.modell, []);
    
      if(els.serie) setSelectOptions(els.serie, []);
    
      setRequiredState(els.her, els.reqHer);
    
      setRequiredState(els.basis, els.reqBasis);
    
      setRequiredState(els.modell, els.reqModell);
    
      if(els.serie && els.reqSerie) setRequiredState(els.serie, els.reqSerie);
    
      els.modellCustom.disabled = true;
    
    
    
      const dot1El = container.querySelector("#dot1");
    
      if(dot1El) dot1El.textContent = "1";
    
      const toStep2Btn = container.querySelector("#toStep2");
    
      if(toStep2Btn) toStep2Btn.disabled = true;
    
      const toStep3Btn = container.querySelector("#toStep3");
    
      if(toStep3Btn) toStep3Btn.disabled = true;
    
      const toStep4Btn = container.querySelector("#toStep4");
    
      if(toStep4Btn) toStep4Btn.disabled = true;
    
      if(els.finish) els.finish.disabled = true;
    
      els.seatGrid.innerHTML = "";
    
      els.seatHint.textContent = "";
    
    
    
      clearSavedState();
  
      updateSummary();
    
      setStep(1);
    
    }
    
  
    // E-Mail Validierung
    function isValidEmail(val){
      return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(val.trim());
    }
  
    function showFieldError(input, msg){
      input.classList.add("invalid");
      let errEl = input.parentNode.querySelector(".field-error");
      if(!errEl){
        errEl = document.createElement("span");
        errEl.className = "field-error";
        input.parentNode.appendChild(errEl);
      }
      errEl.textContent = msg;
      errEl.classList.add("visible");
    }
  
    function clearFieldError(input){
      input.classList.remove("invalid");
      const errEl = input.parentNode.querySelector(".field-error");
      if(errEl) errEl.classList.remove("visible");
    }
  
    (function initEmailValidation(){
      const emailInputs = container.querySelectorAll('input[type="email"]');
      emailInputs.forEach(inp => {
        inp.addEventListener("input", ()=>{
          const val = inp.value.trim();
          if(val.length === 0){
            clearFieldError(inp);
          } else if(!isValidEmail(val)){
            showFieldError(inp, "Bitte eine g\u00fcltige E-Mail-Adresse eingeben.");
          } else {
            clearFieldError(inp);
          }
        });
        inp.addEventListener("blur", ()=>{
          const val = inp.value.trim();
          if(val.length > 0 && !isValidEmail(val)){
            showFieldError(inp, "Bitte eine g\u00fcltige E-Mail-Adresse eingeben.");
          }
        });
      });
    })();
  
    // Erfolgs-Popup
    function showSuccessModal(){
      const overlay = document.createElement("div");
      overlay.className = "success-overlay";
      overlay.innerHTML =
        '<div class="success-modal">' +
          '<div class="success-icon">\u2714</div>' +
          '<h3>Anfrage erfolgreich gesendet!</h3>' +
          '<p>Vielen Dank f\u00fcr Ihre Anfrage. Wir werden uns in K\u00fcrze bei Ihnen melden.</p>' +
          '<button type="button">Schlie\u00dfen</button>' +
        '</div>';
      overlay.querySelector("button").addEventListener("click", ()=> overlay.remove());
      overlay.addEventListener("click", (e)=>{ if(e.target === overlay) overlay.remove(); });
      container.appendChild(overlay);
    }
  
    // Datei-Upload (Fallback-Formular)
    if(els.fallbackImages && els.fallbackImagesTrigger){
      const updateFileInfo = ()=>{
        const files = els.fallbackImages.files;
        let text = "Keine Dateien ausgew\u00e4hlt";
        if(files && files.length === 1){
          text = files[0].name;
        }else if(files && files.length > 1){
          text = files.length + " Dateien ausgew\u00e4hlt";
        }
        if(els.fallbackImagesInfo) els.fallbackImagesInfo.textContent = text;
      };
      els.fallbackImagesTrigger.addEventListener("click", (e)=>{
        e.preventDefault();
        e.stopPropagation();
        els.fallbackImages.click();
      });
      els.fallbackImagesTrigger.addEventListener("touchend", (e)=>{
        e.preventDefault();
        e.stopPropagation();
        els.fallbackImages.click();
      }, { passive: false });
      els.fallbackImages.addEventListener("change", updateFileInfo);
      updateFileInfo();
    }
  
    if(els.serieFallbackForm) els.serieFallbackForm.addEventListener("submit", (e)=>{
  
      e.preventDefault();
  
      const form = els.serieFallbackForm;
      const emailInput = form.querySelector("[name=email]");
      const emailVal = emailInput ? emailInput.value.trim() : "";
  
      if(emailVal && !isValidEmail(emailVal)){
        showFieldError(emailInput, "Bitte eine g\u00fcltige E-Mail-Adresse eingeben.");
        emailInput.focus();
        return;
      }
  
      const submitBtn = form.querySelector('button[type="submit"]');
      if(submitBtn){
        submitBtn.disabled = true;
        submitBtn.textContent = "Wird gesendet\u2026";
      }
  
      const phone = (form.querySelector("[name=phone]") || {}).value || "";
      const vorname = (form.querySelector("[name=vorname]") || {}).value || "";
      const nachname = (form.querySelector("[name=nachname]") || {}).value || "";
      const email = emailVal;
      const message = (form.querySelector("[name=message]") || {}).value || "";
  
      const body = [
        "Sitzbezug Ma\u00dfanfertigung \u2013 Anfrage aus Konfigurator",
        "",
        "Telefon: " + phone,
        "Vorname: " + vorname,
        "Nachname: " + nachname,
        "E-Mail: " + email,
        message ? "\nNachricht:\n" + message : "",
        "",
        "Konfiguration: " + (state.vehicleLabel || "") + " / " + (state.hersteller || "") + " / " + (getFinalModel() || "") + " / " + (state.year || "")
      ].join("\n");
  
      function restoreBtn(){
        if(submitBtn){
          submitBtn.disabled = false;
          submitBtn.textContent = "Anfrage senden";
        }
      }
  
      if(WOMOSI_FALLBACK_SUBMIT_URL){
  
        const fd = new FormData(form);
        fd.set("body", body);
  
        fetch(WOMOSI_FALLBACK_SUBMIT_URL, { method: "POST", body: fd })
          .then(r => r.text().then(text => {
            let ok = true;
            try {
              const data = JSON.parse(text);
              ok = !!(data && data.ok);
            } catch(ex) { /* ignore */ }
            return { ok };
          }))
          .then(({ ok }) => {
            restoreBtn();
            if(ok){
              form.reset();
              if(els.fallbackImagesInfo) els.fallbackImagesInfo.textContent = "Keine Dateien ausgew\u00e4hlt";
              showSuccessModal();
            } else {
              alert("Senden fehlgeschlagen. Bitte versuchen Sie es sp\u00e4ter oder kontaktieren Sie uns per E-Mail.");
            }
          })
          .catch(() => {
            restoreBtn();
            alert("Senden fehlgeschlagen. Bitte versuchen Sie es sp\u00e4ter oder kontaktieren Sie uns per E-Mail.");
          });
  
      } else {
        restoreBtn();
        const mailto = "mailto:?subject=" + encodeURIComponent("Sitzbezug Ma\u00dfanfertigung Anfrage") + "&body=" + encodeURIComponent(body);
        window.location.href = mailto;
      }
  
    });
  
    /* init */
  
    function initFresh(){
      const initToStep2 = container.querySelector("#toStep2");
      if(initToStep2) initToStep2.disabled = true;
      const initToStep3 = container.querySelector("#toStep3");
      if(initToStep3) initToStep3.disabled = true;
      const initToStep4 = container.querySelector("#toStep4");
      if(initToStep4) initToStep4.disabled = true;
      if(els.finish) els.finish.disabled = true;
  
      setSelectOptions(els.her, []);
      setSelectOptions(els.basis, []);
      setSelectOptions(els.modell, []);
      if(els.serie) setSelectOptions(els.serie, []);
      setRequiredState(els.her, els.reqHer);
      setRequiredState(els.basis, els.reqBasis);
      setRequiredState(els.modell, els.reqModell);
      if(els.serie && els.reqSerie) setRequiredState(els.serie, els.reqSerie);
      els.modellCustom.disabled = true;
      setStep(1);
      updateSummary();
    }
  
    function restoreFromCache(){
      const saved = getSavedState();
      if(!saved || !saved.vehicleKey) { initFresh(); return; }
  
      _restoring = true;
  
      state.autoStep3Armed = false;
  
      state.vehicleKey = saved.vehicleKey;
      state.vehicleLabel = saved.vehicleLabel || "";
      state.vehicleUrl = saved.vehicleUrl || "";
  
      container.querySelectorAll("#vehicleGrid .tile-link").forEach(a => {
        if(a.dataset.vehicle === saved.vehicleKey){
          a.classList.add("is-active");
        } else {
          a.classList.remove("is-active");
        }
      });
      const dot1 = container.querySelector("#dot1");
      if(dot1) dot1.textContent = "\u2713";
      const toStep2Btn = container.querySelector("#toStep2");
      if(toStep2Btn) toStep2Btn.disabled = false;
  
      loadHersteller();
  
      if(saved.hersteller){
        els.her.value = saved.hersteller;
        els.herAc.value = saved.hersteller;
        state.hersteller = saved.hersteller;
        if(typeof acHer !== 'undefined') acHer.refresh();
        loadBasis();
  
        if(saved.basis){
          els.basis.value = saved.basis;
          els.basisAc.value = saved.basis;
          state.basis = saved.basis;
          if(typeof acBasis !== 'undefined') acBasis.refresh();
          loadModel();
  
          if(saved.modell){
            els.modell.value = saved.modell;
            els.modellAc.value = saved.modell;
            state.modell = saved.modell;
            if(typeof acModell !== 'undefined') acModell.refresh();
            els.modellCustom.disabled = true;
          } else if(saved.customModell){
            state.customModell = saved.customModell;
            els.modellCustom.value = saved.customModell;
            els.modellCustom.disabled = false;
            els.modell.value = "";
            if(typeof acModell !== 'undefined') acModell.refresh();
          }
        }
      }
  
      if(saved.year){
        state.year = saved.year;
        els.year.value = saved.year;
      }
  
      if(saved.seatId){
        state.seatId = saved.seatId;
        renderSeats();
      }
  
      if(saved.serie){
        state.serie = saved.serie;
        if(state.seatId) loadSerie();
      }
  
      const targetStep = saved.step || 1;
      setStep(targetStep);
      _restoring = false;
      updateSummary();
    }
  
    restoreFromCache();
  
    const resetBtn = container.querySelector("#resetAll");
    if(resetBtn) resetBtn.addEventListener("click", (e)=>{
      e.preventDefault();
      resetAll();
    });
  
    // Error handling for missing elements
    
    window.addEventListener("error", (e)=>{
    
      console.error("Error:", e.message, e.filename, e.lineno);
    
    });
    
    
    
    // Prevent form submission on Enter key in inputs
    
    container.querySelectorAll("input, select").forEach(el => {
    
    el.addEventListener("keydown", (e) => {
    
    if(e.key === "Enter" && el.tagName === "INPUT" && el.type !== "button" && el.type !== "submit"){
    
    e.preventDefault();
    
    }
    
    });
    
    });
    
    });
    
    
    
    })();
   
