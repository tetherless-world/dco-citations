<?php
// This is the API, 2 possibilities: show the app list or show a specific app by id.
// This would normally be pulled from a database but for demo purposes, I will be hardcoding the return values.

function request($url)
{

  // is curl installed?
  if (!function_exists('curl_init')){
    die('CURL is not installed!');
  }

  // get curl handle
  $ch= curl_init();

  // set request url
  curl_setopt($ch,
    CURLOPT_URL,
    $url);

  // return response, don't print/echo
  curl_setopt($ch,
    CURLOPT_RETURNTRANSFER,
    true);

  /*
  Here you find more options for curl:
  http://www.php.net/curl_setopt
  */

  $response = curl_exec($ch);

  curl_close($ch);

  return $response;
}


function get_citation_by_id($id)
{
  $format = 'json';

  $query = "
            PREFIX rdf:   <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
            PREFIX rdfs:  <http://www.w3.org/2000/01/rdf-schema#>
            PREFIX xsd:   <http://www.w3.org/2001/XMLSchema#>
            PREFIX owl:   <http://www.w3.org/2002/07/owl#>
            PREFIX swrl:  <http://www.w3.org/2003/11/swrl#>
            PREFIX swrlb: <http://www.w3.org/2003/11/swrlb#>
            PREFIX vitro: <http://vitro.mannlib.cornell.edu/ns/vitro/0.7#>
            PREFIX bibo: <http://purl.org/ontology/bibo/>
            PREFIX c4o: <http://purl.org/spar/c4o/>
            PREFIX cito: <http://purl.org/spar/cito/>
            PREFIX dcat: <http://www.w3.org/ns/dcat#>
            PREFIX dco: <http://info.deepcarbon.net/schema#>
            PREFIX event: <http://purl.org/NET/c4dm/event.owl#>
            PREFIX fabio: <http://purl.org/spar/fabio/>
            PREFIX foaf: <http://xmlns.com/foaf/0.1/>
            PREFIX geo: <http://aims.fao.org/aos/geopolitical.owl#>
            PREFIX p.1: <http://purl.org/dc/elements/1.1/>
            PREFIX p.2: <http://purl.org/dc/terms/>
            PREFIX obo: <http://purl.obolibrary.org/obo/>
            PREFIX ocrer: <http://purl.org/net/OCRe/research.owl#>
            PREFIX ocresd: <http://purl.org/net/OCRe/study_design.owl#>
            PREFIX p.3: <http://vivoweb.org/ontology/provenance-support#>
            PREFIX prov: <http://www.w3.org/ns/prov#>
            PREFIX skos: <http://www.w3.org/2004/02/skos/core#>
            PREFIX vcard: <http://www.w3.org/2006/vcard/ns#>
            PREFIX vitro-public: <http://vitro.mannlib.cornell.edu/ns/vitro/public#>
            PREFIX vivo: <http://vivoweb.org/ontology/core#>
            PREFIX scires: <http://vivoweb.org/ontology/scientific-research#>

            SELECT ?label ?dcoIdObject
            WHERE
            {
                  ?dcoIdObject a dco:DCOID .
                  ?dcoIdObject rdfs:label '"
            . $id
            . "' .
                  ?p dco:hasDcoId ?dcoIdObject .
                  ?p rdfs:label ?label .
            }
            ";

// DCO-ID for test: 11121/3524-1246-5132-9229-CC

  $searchUrl = 'http://deepcarbon.tw.rpi.edu:3030/VIVO/query?'
    .'query='.urlencode($query)
    .'&format='.$format;

  $responseArray = json_decode(request($searchUrl), true);

  return $responseArray["results"]["bindings"];
}


$possible_url = array("get_citation");

$value = "An error has occurred";

if (isset($_GET["get_citation"]))
{
  $value = get_citation_by_id($_GET["get_citation"]);
}
else if (isset($_GET["action"]))
{
  switch ($_GET["action"])
    {
      case "get_citation":
        if (isset($_GET["id"]))
          $value = get_citation_by_id($_GET["id"]);
        else
          $value = "Missing argument";
        break;
      default:
        $value = $_GET["action"];
    }
}

//return JSON array
exit(json_encode($value));

?>