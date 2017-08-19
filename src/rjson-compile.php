#!/usr/bin/env php
<?php
/**
 * File :: rsjon-compile.php
 *
 * @author    Nicolas DUPRE
 * @release   15/08/2017
 * @version   0.1.0
 * @package   Index
 */


/**
 * Initialisation des variables
 *
 * @var string  $color_err   Code couleur pour les erreurs
 * @var string  $color_in    Code couleur pour les donnée saisie par l'utilisateur
 * @var string  $color_suc   Code couleur pour les success
 * @var string  $color_war   Code couleur pour les warnings
 * @var array   $longopt     Modèle des options version long (--password=Password)
 * @var array   $options     Argument parsé
 * @var string  $workdir     Dossier d'execution courant
 * @var string  $shortopt    Modèle des options admis (-pPassword)
 * @var boolean $stdx        Indique s'il y à eu des sortie STDERR et STDOUT
 */
// Couleurs
$color_err = "196";
$color_in  = "220";
$color_suc = "76";
$color_war = "208";
$color_txt = "221";

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
    "verbose",
    "pretty",
    "preserve-root"
);

// Emplacement
$workdir = $_SERVER["PWD"];

// Flags
$stdx = false;


/**
 * Procède à la conversion des fichiers rjson en json
 *
 * @param string $src
 * @param string $target
 */
function compiler($source, $target){
    global $options, $color_err, $color_txt, $color_suc;
    $json = null;


    /**
     * Traitements des options
     */
    $pretty_print = (isset($options["pretty"])) ? JSON_PRETTY_PRINT : 0;


    /**
     * Récupération des informations sur la sources
     */
    $infos = pathinfo($source);
    $filename = $infos["filename"];


    /**
     * Récupération du contenu du fichier
     */
    $rjson = file_get_contents($source);


    /**
     * Conversion
     */
    // 1. Cleansing
    // 1.1. Sécurisation du modèle commentaire dans les block textes
    $rjson = preg_replace_callback("#[\"']\K(.*)[\"']#U", function($matches){
        return preg_replace("#\/#", "&slash;", $matches[0]);
    }, $rjson);
    // 1.2. Suppression des commentaires en ligne
    $rjson = preg_replace("#\/\/.*#", "", $rjson);
    // 1.3. Suppression des commentaire en block
    $rjson = preg_replace("#\/\*+(.*\s)*\*+\/#U", "", $rjson);
    // 1.4. Suppression des lignes vide
    $rjson = preg_replace("#^\s*\n#", "", $rjson);
    // 1.5. Restitution des slash modifier
    $rjson = preg_replace("#&slash;#", "/", $rjson);

    // 2. Récuoération des variables déclarée

    // 3. Récupération des structures
    preg_match_all("#(.*)\s+=\s+({(\s*.*)*});#U", $rjson, $matches);
    // Match 0 :: [var] name = {structure}
    // Match 1 :: [var] name
    // Match 2 :: {structure}
    // Match 3 :: empty
    $jsons = Array();

    foreach ($matches[1] as $index => $match){
        // Supprimer le mot clef "var" si présent
        $match = preg_replace("/^var\s+/", "", $match);

        // Recupération de la structure associée
        $structure = $matches[2][$index];

        // JSON_DECODAGE
        $jsons[$match] = json_decode($structure, true);
        if(json_last_error()) stderr("$color_err>%s for %s in the following JSON structure : $color_txt>%s", [json_last_error_msg(), $match, $structure], 1);
    }

    // 4. Complétion des références et variables
    array_walk_recursive($jsons, function(&$setted) use ($jsons){
        // Est-ce une référence
        if(preg_match("/^&/", $setted)){
            // Resolution de la valeur
            $setted = resolver($setted, $jsons, $setted);
        }
    });

    // 5. Converson au format JSON
    $json = json_encode($jsons, $pretty_print);


    /**
     * Enregistrement
     */
    // STDOUT
    if(is_null($target)){
        fwrite(STDOUT, $json.PHP_EOL);
    }
    // DEPOSIT
    if(is_array($target)){
        foreach($target as $index => $value){
            if(file_exists($value)){
                if(is_dir($value)){
                    file_put_contents("$value/$filename.json", $json);
                }
                if(is_file($value)){
                    file_put_contents($value, $json);
                }
            } else {
                // Modele spécifiant un dossier cible
                if(preg_match("#\/$#", $value)){
                    mkdir($value, 0755, true);
                    file_put_contents("$value/$filename.json", $json);
                }
                else {
                    file_put_contents($value, $json);
                }
            }
        }
    }
    // FILE / FOLDER
    if(is_string($target)){
        if(file_exists($target)){
            if(is_dir($target)){
               file_put_contents("$target/$filename.json", $json);
            }
            if(is_file($target)){
                file_put_contents($target, $json);
            }
        } else {
            // Modele spécifiant un dossier cible
            if(preg_match("#\/$#", $target)){
                mkdir($target, 0755, true);
                file_put_contents("$target/$filename.json", $json);
            }
            else {
                file_put_contents($target, $json);
            }
        }
    }
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
        --pretty       Use whitespace in returned data to format it.


HowTo Examples :
    • Common Usage : Compile one rsjon file :
        CMD    : rjson-compile -i my-file.rjson
        RESULT : Compile my-file.rjson to my-file.json

    • Common Usage : With Output
        CMD    : rjson-compile -i my-file.rjson -o compiled.json
        RESULT : COmpile my-file.rjson to compiled.json
        
HELP;
    echo PHP_EOL;
}

/**
 * Met en évidence les valeurs utilisateur dans les messages
 *
 * @param  string $message   Message à analyser
 *
 * @return string $message   Message traité
 */
function highlight($message){
    global $color_in;

    // A tous ceux qui n'ont pas de couleur spécifiée, alors saisir la couleur par défaut
    $message = preg_replace("/(?<!>)(%[a-zA-Z0-9])/", "$color_in>$1", $message);

    // Remplacer par le code de colorisation Shell
    $message = preg_replace("#([0-9]+)>(%[a-zA-Z0-9])#", "\e[38;5;$1m$2\e[0m", $message);

    return $message;
}

/**
 * Analyse de la cohérance des options et de leur disponibilité
 *
 * @param string $directory
 *
 * @return void
 */
function parser($directory){
    global $options;
    //static $outputs = null;
    //static $each_target = false;
    $deposit = false;

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
    if(count($outputs) > 0 && count($sources) !== count($outputs)){
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

        $deposit = true;
    }


    /**
     * Processing des sources
     */
    foreach ($sources as $index => $source){
        // Déterminer la cible qui convient
        $target = null;
        if($deposit){
            $target = $outputs;
        } else {
            $target = $outputs[$index];
        }

        processor($source, $target);
    }
}

/**
 * Gestionnaire de lecture des entrée pour déclencher les compilation
 *
 * @param string $source   Chemin absolu vers la source à traiter
 * @param mixed  $target   Emplacement de dépot
 */
function processor($source, $target){
    global $options;
    $recursively = false;

    /**
     * Vérification de l'option "Recursive"
     */
    if(isset($options["r"]) || isset($options["recursive"])) $recursively = true;

    /**
     * Traitement de la source
     */
    if(is_dir($source)){
        $files = scandir($source);

        foreach($files as $index => $file){
            if(is_dir("$source/$file")){
                if($recursively) processor($source, $target);
            } else {
                if(preg_match("#\.rjson$#", $file)){
                    compiler("$source/$file", $target);
                }
            }
        }
    }
    else {
        compiler($source, $target);
    }
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
 * Résoud les différents pointeur interne
 *
 * @param string   $reference  Référence à résoudre (ex: &cfg.db.user)
 * @param array    $source     Jeu de donnée de base dans lequel trouver &cfg.db.user
 * @param string   $root       Référence d'origine - Permet l'antibloucle infinie
 * @param bool     $first      Indique que c'est l'appel d'origine (par une recursion)
 *
 * @return string  Retourne la valeur traitée
 */
function resolver($reference, $source, $root, $first=true){
    global $color_err, $color_suc;

    $value = null;
    $from = null;
    $path = null;
    $path_resolved = null;
    $last_level = null;
    $has_broken = false;

    // 1. Controle anti-boucle
    if(!$first && $reference === $root){
        stderr("By resolution, the reference %s point on itself. Resolution kill to prevent infinite loop", [$root], 1);
    }

    // 2. Suppression de l'indicateur de référence
    $reference = preg_replace("/^&/", "", $reference);

    // 3. Exploser en chemin (path)
    $path = preg_split("/\./", $reference);

    foreach ($path as $index => $level){
        $last_level = $level;
        // Déterminer la source de lecture
        $from = (is_null($value)) ? $source : $value;

        // Si le niveau n'existe pas, on ne peux pas résoudre la référence
        if(!isset($from[$level])){
            // "A été intérompu"
            $has_broken = true;
            break;
        }

        // Sinon on récupère la valeur
        $value = $from[$level];

        // Sauvegarde le chemin parcouru
        $path_resolved .= (is_null($path_resolved)) ? $level : ".$level";
    };

    // 4. Contrôle de résolution
    if($has_broken) stderr("Can't find pointed value %s [$color_err>%s]. The engine can only resolve $color_suc>%s", [$reference, $last_level, $path_resolved], 1);

    // 5. Controle de la valeur obtenue
    if(preg_match("/^&/", $value)){
        $value = resolver($value, $source, $root, false);
    }

    return $value;
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