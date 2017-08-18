# rjson_compile

Le format ``rjson`` est une invention de ma part. Il n'à rien avoir avec les fichiers **JSON** pour **R**. 

``rjson`` vaut pour **R**eferenceable **J**ava **S**cript **O**bject **N**otation.

**Referencable** ne signifie pas que le fichier peut être référencé, mais qu'il permet l'utilisation de référence interne. Cela permet d'éviter la dupplication inutile de valeur et de créer des clé générique qui seront pointée.

Ce format offre également la possibilité d'intégrer les commentaires de Javascript :

```javascript
// Commentaire en ligne

/* Commentaire
Multi
Ligne */
```


## Système de référence interne

``&`` référence interne

``$`` appel une variable global


## Utilisation

### Aucun argument

### Un argument

### Deux arguments



## Colorisation Syntaxique

### GitLab


### PhpStorm
