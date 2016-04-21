<?php
header("Access-Control-Allow-Origin: *");

$valid_types = array( "Journal Article", "Conference Paper", "Conference Poster" ) ;

// This is the API, 2 possibilities: show the app list or show a specific app by id.
// This would normally be pulled from a database but for demo purposes, I will be hardcoding the return values.

// make the curl query to elasticsearch
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
    $pos = strpos($id, '/');
    $dcoid = substr( $id, $pos + 1 ) ;
    $searchUrl = "http://localhost:49200/dco/publication/_search?q=_id:$dcoid";

    $responseArray = json_decode(request($searchUrl), true);

    return $responseArray;
}

// grab the dcoid from the http request
$dcoid = "";

if (isset($_GET["get_citation"]))
{
    $dcoid = $_GET["get_citation"];
}
else if (strpos($_SERVER["PATH_INFO"],"/11121/") == 0)
{
    $dcoid = substr( $_SERVER["PATH_INFO"], 1 );
}
else if (isset($_GET["action"]))
{
    switch ($_GET["action"])
    {
        case "get_citation":
            if (isset($_GET["id"]))
                $dcoid = $_GET["id"];
            break;
        default:
            $value = $_GET["action"];
            break;
    }
}

if( empty( $dcoid ) ) 
{
    exit( "No publication DCO-ID was specified" ) ;
}

// given the dcoid go get the publication from elasticsearch
$value = get_citation_by_id($dcoid);

// see if there was a hit for the dcoid
if( !isset( $value["hits"] ) || !isset( $value["hits"]["hits"] ) || count( $value["hits"]["hits"] ) != 1 || !isset( $value["hits"]["hits"][0]["_source"] ) )
{
    exit( "Unable to find information about $dcoid" ) ;
}

$pub = $value["hits"]["hits"][0]["_source"] ;

// right now we can only generate citations for certain types
if( !in_array( $pub["mostSpecificType"], $valid_types ) )
{
    exit( "Unable to generate citation for this publication (type: ".$pub["mostSpecificType"].")" ) ;
}

// build the full dcoid
$dx = "http://dx.deepcarbon.net/".$dcoid;

// get the list of authors. They are already ordered in elasticsearch
$authors = array();
if( isset( $pub["authors"] ) && count( $pub["authors"] ) != 0 )
{
    foreach( $pub["authors"] as $author )
    {
        array_push( $authors, $author["name"] ) ;
    }
}

// now we generate the citation
$citation = "";
$html = "<link rel=\"stylesheet\" type=\"text/css\" href=\"/css/citation.css\"><div class=\"citationbody\">" ;

// the authors go first and should be in order
$isfirst = true ;
foreach($authors as $author)
{
    if( $isfirst ) {
        $citation .= $author;
        $isfirst = false;
    } else {
        $citation .= ", ".$author;
    }    
}

// if there's a year then add it
if( isset( $pub["publicationYear"] ) )
{
    $citation .= " (" . $pub["publicationYear"] . ") " ;
}

// if there's a doi then use it as the publication's link. Otherwise use the dcoid.
if( isset( $pub["doi"] ) )
{
    $link = "https://dx.doi.org/" . $pub["doi"] ;
} else {
    $link = $dx ;
}

// add the title with the link
$citation .= " <a class=\"citationlink\" href=\"$link\">" . $pub["title"] . "</a>.";

// if there's a publication venue (journal, book or whatever) then add it
if( isset( $pub["publishedIn"] ) )
{
    $citation .= " " . $pub["publishedIn"]["name"] ;
}
elseif (isset( $pub["presentedAt"] )) {
    $citation .= " " . $pub["presentedAt"]["name"] ;
}

// volue(issue):start-end
if( isset( $pub["volume"] ) )
{
    $citation .= " " . $pub["volume"] ;
}

if( isset( $pub["issue"] ) )
{
    $citation .= "(" . $pub["issue"] . ")";
}

if( isset( $pub["pageStart"] ) )
{
    $start = $pub["pageStart"] ;
}

if( isset( $pub["pageEnd"] ) )
{
    $end = $pub["pageEnd"] ;
}
if( isset($start) || isset($end) )
{
    $citation .= ":" ;
    if( isset($start) ) $citation .= $start ;
    if( isset($end) ) $citation .= "-" . $end ;
}

$html .= "$citation</div>";

// and we're done, exit with the generated citation
//exit(json_encode($pub));
exit($html);

?>
