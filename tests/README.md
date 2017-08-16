# Liste des tests

## Test 01 :

* Un ``fichier`` source : **config.rjson**.

rjson-compile -i config.rjson


## Test 02 :

* Un ``dossier`` sources : dossier courant.
    * config.rjson

rjson-compile -i .


## Test 03 :

* Deux ``fichiers`` source : **config.rjson** et **settings.rjson**.

rjson-compile -i config.rjson -i settings.rjson


## Test 02 :

* Deux ``dossiers`` sources : **DIR1** et **DIR2**.
    * DIR1
        * config.rjson
        * settings.rjson
    * DIR2
        * connexion.rjson
        * credentials.rjson

rjson-compile -i DIR1 -i DIR2