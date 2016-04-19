# dco-citations
Generate formatted publication citation from DCO ID.

The citation service is installed under /var/www/html/info for info.deepcarbon.net document root. The css/citation.css also goes under
/var/www/html/info/css.

A start will be having a serving that when user enters in the address bar, for instance, "`http://dx.deepcarbon.net/11121/2803-7494-8579-1947-CC?citation=PNAS`", the webpage then will show the user the citation generated in PNAS format for the corresponding publication.


Use DCO-ID "11121/3524-1246-5132-9229-CC" for our example.

```http://localhost/api.php?get_citation=11121/3524-1246-5132-9229-CC``` will successfully return information on the DCO-ID provided such as 

"[{"label":{"type":"literal","value":"A Jigsaw Puzzle Layer Cake of Spatial Data"},"dcoIdObject":{"type":"uri","value":"http:\/\/dx.deepcarbon.net\/11121\/3524-1246-5132-9229-CC"}}]"

By setting rewriting rules in ```.htaccess``` file, we want to achieve url rewriting such that 

```http://localhost/citation/11121/3524-1246-5132-9229-CC``` will yield the same result.
