#!/usr/bin/php
<?php
/**
 * File :: rsjon-compile.php
 *
 * @author    Nicolas DUPRE
 * @release   15/08/2017
 * @version   1.0.0
 * @package   Index
 */


/**
 * Initialisation des variables
 *
 * @var string $color_err   Code couleur pour les erreurs
 * @var string $color_in    Code couleur pour les donnée saisie par l'utilisateur
 * @var string $color_suc   Code couleur pour les success
 * @var string $color_war   Code couleur pour les warnings
 * @var array  $longopt     Modèle des options version long (--password=Password)
 * @var array  $options     Argument parsé
 * @var string $workdir     Dossier d'execution courant
 * @var string $shortopt    Modèle des options admis (-pPassword)
 */
// Couleurs
$color_err = "196";
$color_in  = "220";
$color_suc = "76";
$color_war = "208";

// Options
$options = null;
$shortopt = "hi:o:rmpv";
$longopt = Array(
    "help",
    "in:",
    "out:",
    "recursive",
    "merge",
    "progress",
    "verbose"
);

// Emplacement
$workdir = $_SERVER["PWD"];


/**
 * Procède à la conversion des fichiers rjson en json
 *
 * @param string $src
 * @param string $target
 */
function compiler($src, $target = null){
    global $options;

    stdout("%s vers %s", [$src, $target]);
}

/**
 * Affiche le manuel d'aide
 *
 * @eturn void
 */
function help(){
    echo <<<HELP

Usage : rjson-compile [OPTIONS] -i in_file [-o out_file]

Compile rjson file to json. Can be process files or folders recusively.
It can merge rjson file into one json file.


List of all available argument for docotomate

    -h, --help         Display this help text and exit
    -i, --in=SRC       Source input to compile (File or Directory)
    -o, --out=DEST     Output destination (File or Directory)                     
    -r, --recursive    Read source folder recursively
    -m, --merge        If your output is file whereas your process a directory
                       you will need to merge them
    -p, --progress     Show progress barre


HowTo Examples :
    • Common Usage : Compile one rsjon file :
        CMD    : rjson-compile -i my-file.rjson
        RESULT : Compile my-file.rjson to my-file.json

    • Common Usage : With Output
        CMD    : rjson-compile -i my-file.rjson -o compiled.json
        RESULT : COmpile my-file.rjson to compiled.json
        
HELP;
}

/**
 * @param $message
 * @return mixed
 */
function highlight($message){
    global $color_in;

    // Entourer tout les specificateur de type par le code de colorisation
    $message = preg_replace("#(%[a-zA-Z0-9])#", "\e[38;5;{$color_in}m$1\e[0m", $message);

    return $message;
}

/**
 * Analyse de la cohérance des options et de leur disponibilité
 *
 * @param string $directory
 * @param string $input     Si défini, alors la fonction se comporte comme une recusrion
 *
 * @return void
 */
function parser($directory, $read_options = true){
    global $options;
    static $outputs = null;
    static $each_target = false;

    // Définition des sources et des sorties
    if($read_options) {
        /**
         * Analyse des Inputs
         */
        // Chercher les fichier sources à traiter (input)
        $input = @($options["i"]) ?: @$options["in"];

        // Passer l'entrée en tableau pour standardiser le traitements des sources
        $sources = (is_array($input)) ? $input : Array($input);

        // Completion des chemins en absolu
        array_walk($sources, function (&$el) use ($directory) {
            // Si l'élement n'est pas une source absolue
            if (!preg_match("#^\/#", $el)) $el = $directory . "/" . $el;
        });

        // Dédoublonner
        $sources = array_unique($sources);


        /**
         * Analyse des sorties
         */
        // Chercher la/les sortie(s) (output)
        $outputs = @($options["o"]) ?: @$options["out"];

        // Si une seule valeur fournie elle est au format chaine
        if(is_string($outputs)) $outputs = [$outputs];

        // Completion des chemins en absolu
        if(is_array($outputs)) array_walk($outputs, function(&$el) use ($directory){
            // Si l'élement n'est pas une source absolue
            if (!preg_match("#^\/#", $el)) $el = $directory . "/" . $el;
        });

        // Dédoublonner
        if(is_array($outputs)) $outputs = array_unique($outputs);


        /**
         * Analyser l'existance des Fichiers et Dossiers sources
         * Les sorties seront créée si elles n'existent pas
         */
        foreach($sources as $key => $value){
            if(!file_exists($value)) stderr("The following source %s doesn't exist.", [$value], 1);
        }


        /**
         * Analyser le système de sortie
         */
        // Si pas de sortie spécifiée (null), alors vers STDOUT pour tous

        // Si autant de sorties que de sources, alors pas de soucis
        //  - FICHIER >> FICHIER (create si not exist)
        //  - FICHIER >> DOSSIER (create full path si not exist)
        //
        //  - DOSSIER >> FICHIER (Nécéssite --merge)
        if(count($sources) === count($outputs)){
            // Chercher un système DOSSIER VERS FICHIER
            foreach($sources as $index => $source){
                $merge_required = false;
                if(is_dir($source)){
                    // Soit la cible existe et on peu identifier
                    if(file_exists($outputs[$index]) && is_file($outputs[$index])) $merge_required = true;
                    // Soit c'est la notation qui fais fois
                    if(!file_exists($outputs[$index]) && !preg_match("#\/$#",$outputs[$index])) $merge_required = true;
                    if($merge_required && (!isset($options["merge"]) && !isset($options["m"]))) stderr(
                        "The input folder %s will be compile entirely toward the file %s. To confirm the behavior, please use %s option.",
                        [$source, $outputs[$index], '--merge'],
                        1
                    );
                }
            }
        }

        // Si Différences entre les entrées et les sorties, alors le tout est duppliqué dans chaque sortie1
        // - Il doit s'agir que de dossier de sortie
        // - Si des fichiers, alors --merge required
        if(count($sources) !== count($outputs)){
            // Chercher la présence de fichier afin d'emettre une alerte sur le comportement
            $merge_required = false;
            foreach ($outputs as $index => $value){
                if(file_exists($value) && is_file($value)) $merge_required = true;
                if(!file_exists($value) && !preg_match("#\/$#", $value)) $merge_required = true;
                if($merge_required && (!isset($options["merge"]) && !isset($options["m"]))) stderr(
                    "The different input will be compile entirely toward the file %s. To confirm the behavior, please us %s option.",
                    [$value, "--merge"],
                    1
                );
            }

            $each_target = true;
        }
    }






        //// Parcourir les sources
        //foreach ($sources as $index => $source) {
        //    // Controler l'existance de la source demandé
        //    if (file_exists($source)) {
        //        // Contrôler le type de source : FILE ou DIR
        //        if (is_dir($source)) {
        //
        //        } else {
        //            // Procéder à la compilation du fichier
        //            compiler($source);
        //        }
        //    } else {
        //        // Ne peux qu'échouer dans l'appel initial.
        //        // Si pas read_option c'est récursif et appelé par programme
        //        if ($read_options) stderr("File or folder %s doesn't exist", [$source], 1);
        //    }
        //}

    // // Controler l'existance du fichier/dossier demandé
    //     // Vérifier si c'est un ficier ou un dossier
    //     if(is_dir($full_path)){
    //         // Analyser le dossier

    //         //// Si l'option "recursive" est définie, alors analyser le sous dossier.
    //         //if(isset($options["r"]) || isset($options["recursive"])){
    //         //    true;
    //         //}
    //     } else {
    //         // Vérifier si un fichier cible est défini
    //         $target = @($options["o"]) ?: @($options["out"]) ?: null;
    //     }
}

/**
 * Affiche une barre de progression lors de la compilation
 *
 * @param $current
 * @param $total
 */
function progress($current, $total){

}

/**
 * Emet des messages dans le flux STDERR de niveau WARNING ou ERROR
 *
 * @param string $message Message à afficher dans le STDERR
 * @param array  $args    Elements à introduire dans le message
 * @param int    $level   Niveau d'alerte : 0 = warning, 1 = error
 */
function stderr($message, $args, $level = 1){
    // Connexion aux variables globales
    global $color_err, $color_war;

    // Traitement en fonction du niveau d'erreur
    $level_str = ($level) ? "ERROR" : "WARNING";
    $color = ($level) ? $color_err : $color_war;

    // Mise en evidence des saisie utilisateur
    $message = highlight($message);
    $message = "[ \e[38;5;{$color}m$level_str\e[0m ] :: $message".PHP_EOL;

    fwrite(STDERR, vsprintf($message, $args));
    if($level) die($level);
}

/**
 * Emet des messages dans le flux classique STDOUT
 *
 * @param string $message Message à afficher dans le STDOUT
 * @param array  $arg     Elements à introduire dans le message
 */
function stdout($message, $args){
    global $options;

    if(isset($options["v"]) || isset($options["verbose"])){
        $message = highlight($message);
        $message = "[ INFO ] :: $message".PHP_EOL;
        fwrite(STDOUT, vsprintf($message, $args));
    }
}


/**
 * Récupération des arguments
 */
$options = getopt($shortopt, $longopt);


/**
 * Executions du script
 */
// Afficher l'aide
if(isset($options["h"]) || isset($options["help"])) help();

// Compilation
(isset($options["i"]) || isset($options["in"])) ? parser($workdir) : help();


/**
 * Forcer le retour à la ligne de la console
 */
echo PHP_EOL;


