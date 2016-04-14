<?php
header("Access-Control-Allow-Origin: *");
// This is the API, 2 possibilities: show the app list or show a specific app by id.
// This would normally be pulled from a database but for demo purposes, I will be hardcoding the return values.

// make the curl sparql query request
function request($url)
{
    // is curl installed?
    if (!function_exists('curl_init')){
        die('CURL is not installed!');
    }

    // get curl handle
    $ch= curl_init();

    // set request url
    curl_setopt($ch, CURLOPT_URL, $url);

    // return response, don't print/echo
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

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
    PREFIX bibo: <http://purl.org/ontology/bibo/>
    PREFIX dco: <http://info.deepcarbon.net/schema#>
    PREFIX foaf: <http://xmlns.com/foaf/0.1/>
    PREFIX vivo: <http://vivoweb.org/ontology/core#>

    DESCRIBE ?p ?dcoid ?author_role ?author ?journal
    WHERE
    {
        ?dcoid a dco:DCOID .
        ?dcoid rdfs:label ?id .
        ?p dco:hasDcoId ?dcoid .
        { ?p a bibo:Article . } UNION 
        { ?p a bibo:Book . } UNION 
        { ?p a bibo:DocumentPart . } UNION 
        { ?p a dco:Poster . } UNION 
        { ?p a bibo:Thesis . }
        ?p vivo:relatedBy ?author_role .
        ?author_role a vivo:Authorship .
        ?author_role vivo:relates ?author .
        FILTER (?author != ?p)
        ?author a foaf:Person .
        OPTIONAL {
            ?p vivo:hasPublicationVenue ?journal .
            ?journal a bibo:Journal .
        }
    }
    ";

    $query = str_replace("?id", '"'.$id.'"', $query);

    // DCO-ID for test: 11121/3524-1246-5132-9229-CC

    $searchUrl = 'http://deepcarbon.tw.rpi.edu:3030/VIVO/query?'
                .'query='.urlencode($query)
                .'&output='.$format;

    $responseArray = json_decode(request($searchUrl), true);

    return $responseArray;
}

function format_name($name)
{
    $name = explode(",", $name);
    return trim($name[0])." ".substr(trim($name[1]),0,1);
}

$value = "An error has occurred";

$dcoid = "";

if (isset($_GET["get_citation"]))
{
    $dcoid = $_GET["get_citation"];
}
else if (isset($_GET["action"]))
{
    switch ($_GET["action"])
    {
        case "get_citation":
            if (isset($_GET["id"]))
                $dcoid = $_GET["id"];
            else
                $value = "Missing argument";
            break;
        default:
            $value = $_GET["action"];
    }
}

if( !empty( $dcoid ) ) 
    $value = get_citation_by_id($dcoid);

if ($value == null)
    exit("Invalid DCO-ID!");

// build the full dcoid
$dx = "http://dx.deepcarbon.net/".$dcoid;

// find the publication object in the json document
$pub=null;
foreach( $value as $puburi => $thingy )
{
    if(isset($thingy["http://info.deepcarbon.net/schema#hasDcoId"]) && $thingy["http://info.deepcarbon.net/schema#hasDcoId"][0]["value"] == $dx)
    {
        $pub = $thingy ;
        break ;
    }
}

// get the list of authors. pub -> relatedBy -> authorRole -> relates -> person
// there's also a relates back to the publication so don't want that one
$authors = array();

// if there's only one author then there won't be a rank, so start with 0. Otherwise rank starts with 1
$rank = 0 ;
if( isset( $pub["http://vivoweb.org/ontology/core#relatedBy"] ) )
{
    $authorRoles = $pub["http://vivoweb.org/ontology/core#relatedBy"];
    foreach( $authorRoles as $role )
    {
        $roleuri = $role["value"];
        if( isset( $value[$roleuri] ) )
        {
            $relates = $value[$roleuri]["http://vivoweb.org/ontology/core#relates"];
            if( isset( $value[$roleuri]["http://vivoweb.org/ontology/core#rank"] ) )
            {
                $rank = $value[$roleuri]["http://vivoweb.org/ontology/core#rank"][0]["value"];
            }
            $author = null ;
            foreach( $relates as $relate )
            {
                if( $relate["value"] != $puburi )
                {
                    $author = $value[$relate["value"]];
                    $name = $author["http://www.w3.org/2000/01/rdf-schema#label"][0]["value"];
                    $authors[intval($rank)] = format_name($name);
                }
            }
        } else {
            $authors[intval($rank)] = "Missing" ;
        }
    }
}
ksort( $authors ) ;

// now we generate the citation
$citation = "";

// the authors go first and should be in order
$isfirst = true ;
foreach($authors as $key => $author)
{
    if( $isfirst ) {
        $citation .= $author;
        $isfirst = false;
    } else {
        $citation .= ", ".$author;
    }    
}

// if there's a year then add it
if( isset( $pub["http://info.deepcarbon.net/schema#yearOfPublicationYear"] ) )
{
    $citation .= " (" . $pub["http://info.deepcarbon.net/schema#yearOfPublicationYear"][0]["value"] . ") " ;
} else if( isset( $pub["http://info.deepcarbon.net/schema#yearOfPublication"] ) )
{
    $citation .= " (" . $pub["http://info.deepcarbon.net/schema#yearOfPublication"][0]["value"] . ")" ;
}

// if there's a doi then use it as the publication's link. Otherwise use the dcoid.
if( isset( $pub["http://purl.org/ontology/bibo/doi"] ) )
{
    $link = "https://dx.doi.org/" . $pub["http://purl.org/ontology/bibo/doi"][0]["value"] ;
} else {
    $link = $dx ;
}

// add the title with the link
$citation .= " <a href=\"$link\">" . $pub["http://www.w3.org/2000/01/rdf-schema#label"][0]["value"] . "</a>.";

// if there's a publication venue (journal, book or whatever) then add it
if( isset( $pub["http://vivoweb.org/ontology/core#hasPublicationVenue"] ) )
{
    $venue = $value[$pub["http://vivoweb.org/ontology/core#hasPublicationVenue"][0]["value"]] ;
    $venueLabel = $venue["http://www.w3.org/2000/01/rdf-schema#label"][0]["value"];
    $citation .= " " . $venueLabel ;
}

// volue(issue):start-end
if( isset( $pub["http://purl.org/ontology/bibo/volume"] ) )
{
    $volume = $pub["http://purl.org/ontology/bibo/volume"][0]["value"];
    $citation .= " " . $volume ;
}

if( isset( $pub["http://purl.org/ontology/bibo/issue"] ) )
{
    $issue = $pub["http://purl.org/ontology/bibo/issue"][0]["value"];
    $citation .= "(" . $issue . ")";
}

if( isset( $pub["http://purl.org/ontology/bibo/pageStart"] ) )
{
    $start = $pub["http://purl.org/ontology/bibo/pageStart"][0]["value"];
}

if( isset( $pub["http://purl.org/ontology/bibo/pageEnd"] ) )
{
    $end = $pub["http://purl.org/ontology/bibo/pageEnd"][0]["value"];
}
if( isset($start) || isset($end) )
{
    $citation .= ":" ;
    if( isset($start) ) $citation .= $start ;
    if( isset($end) ) $citation .= "-" . $end ;
}

// and we're done, exit with the generated citation
//exit(json_encode($pub));
exit($citation);

?>
