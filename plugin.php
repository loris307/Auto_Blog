<?php

 /*
  * Plugin Name:       Automatischer Beitrag
  * Description:       Ein Plugin, das automatisch, wöchentlich neue Blogbeiträge erstellt
  * Version:           1.0
  * Requires at least: 5.2
  * Requires PHP:      7.2
  * Author:            Loris Galler
  */

require __DIR__ . '/vendor/autoload.php';


function create_auto_post() {


    $articles = get_news_articles();

    $articleTitle = $articles[0]['title'];
    $articleDescription = $articles[0]['description'];
    $keyword = generate_keyword($articleTitle, $articleDescription);

    $imageURL = getting_image($keyword);

    $blogPostContent = generate_blog_post($articles, $imageURL);
    eval($blogPostContent);

    if (isset($mein_beitrag) && is_array($mein_beitrag)) {
        $postId = wp_insert_post($mein_beitrag);
        echo "Beitrag mit der ID $postId erstellt.";
    }

    if ($postId && !is_wp_error($postId)) {
        add_image($postId, $imageURL);
    }




}

function get_news_articles() {

    $apiKeyNews = 'DEIN_API_SCHLÜSSEL';
    $client = new GuzzleHttp\Client();

    $dateToday = new DateTime(); // today date
    $dateFourDaysAgo = $dateToday->sub(new DateInterval('P4D'))->format('Y-m-d'); // date 4 days ago

    try {
    $response = $client->request('GET', 'https://newsapi.org/v2/everything', [
        'query' => [
            'q' => 'Finanzen OR Aktien OR Zinsen OR Rendite OR Sparen OR Altersvorsorge OR Versicherungen', // keywords
            'language' => 'de',
            'sortBy' => 'relevancy',
            'from' => $dateFourDaysAgo,
            'apiKey' => $apiKeyNews,
            'pageSize' => 1,
        ]
    ]);

    $statusCode = $response->getStatusCode();
    $content = $response->getBody()->getContents();
    $news = json_decode($content, true);


    $articles = [];
    if ($statusCode === 200 && isset($news['articles'])) {
        foreach ($news['articles'] as $article) {
            $articles[] = [
                'title' => $article['title'],
                'description' => $article['description'],
                'content' => $article['content'] ?? 'Inhalt nicht verfügbar',
                'url' => $article['url'],
                'image' => $article['urlToImage'] ?? 'Bild nicht verfügbar',
            ];
        }
    }
    return $articles;

    } catch (\Exception $e) {
    echo 'Fehler beim Abrufen der News: ' . $e->getMessage();
    return []; // Rückgabe eines leeren Arrays im Fehlerfall
    }

}

function generate_keyword($articleTitle, $articleDescription){

    if ($articleTitle == null || $articleDescription == null) {
        return null;
    }

    $apiKey = 'DEIN_API_SCHLÜSSEL';
    $client = new GuzzleHttp\Client();
    


    $systemPrompt = <<<'PROMPT'

    Du bekommst vom User 2 Informationen. Einen Titel von einer News und eine Beschreibung des Inhalts.
    Du sollst dann aus diesen Text ein geeignetes Keyword rausfiltern, unter dessen man diesen artikel einordnen kann. 
    Das bedeutet, wenn es um Aktien geht, dann soll das Keyword Aktien sein, wenn es um Finanzen geht dann einfach nur 
    Finanzen und so weiter. Du antwortest immer mit genau EINEM Wort, das word MUSS in English sein und lowercase! 
    
    PROMPT;


    try {
        $response = $client->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role'=> 'system', 'content'=> $systemPrompt],
                    ['role' => 'user', 'content' => 'Titel:' . $articleTitle . 'Beschreibung:' . $articleDescription,
                    ],
                ],
            ],
        ]);

        $body = $response->getBody();
        $content = json_decode($body, true);

        

        return $content['choices'][0]['message']['content'];
    
    } catch (\Exception $e) {
        error_log('Error: ' . $e->getMessage());
        return null;
    }


}

function getting_image($keyword) {

    if ($keyword == null) {
        return null;
    }
    
    $api_key_pexels = 'DEIN_API_KEY';
    $client = new GuzzleHttp\Client();

    try {
        $response = $client->request('GET', $url, [
            'query' => [
                'query' => $keyword,
                'per_page' => 15, 
            ],
            'headers' => [
                'Authorization' => $api_key_pexels
            ]
        ]);

        $body = $response->getBody();
        $data = json_decode($body, true);
        if (!empty($data['photos'])) {
            $randomIndex = array_rand($data['photos']); //pick random
            return $data['photos'][$randomIndex]['src']['original'];
        }
    } catch (\Exception $e) {
        error_log('Error: ' . $e->getMessage());
    }

    return null; 


}

function generate_blog_post($articles, $imageURL) {


    if($articles == null || $imageURL == null){
        return null;
    }

    $apiKey = 'DEIN_API_SCHLÜSSEL';
    $client = new GuzzleHttp\Client();

    $systemPrompt = <<<'PROMPT'
    
    Du bist ein WordPress Blogbeiträge Ersteller. Der User wird dir den Titel, Beschreibung und ein Teil des Contents einer Nachricht geben.
    Außerdem bekommst du die Quelle des Beitrags als link. Diesen Link sollst du am Ende des 'post_content' unter einem Schriftzug "Mehr erfahren" 
    verlinken, davor lässt du eine Zeile frei. Danach fügst du noch die Bildquelle ein, wofür du 3 leere Zeilen machst und dann unter dem 
    Schriftzug "Bildquelle: Pexels" die Quelle des Bildes verlinkst. Du wirst aus diesen Infos einen sinnvollen, interessanten und mit eigener 
    Meinung geschriebenen Blogpost machen. Füge kleine Zwischenüberschriften ein um die Lesbarkeit zu erhöhen, diese sollten aber die selbe 
    Formatierung wie der Rest des Textes haben, also NICHT fett-gedruckt, mit ** oder ähnliches.
    Eine passendes Format wäre: 

    Überschrift 

    Text

    Überschrift 

    Text 

    …

    Damit ich es direkt implementieren kann muss deine Ausgabe genau dieses Format haben:

    $mein_beitrag = array(
        'post_title'    => wp_strip_all_tags('Kreativer Titel des Blogpost'),
        'post_content'  => 'Dies ist der Inhalt des Blogpost. [...] 
            <a href="[HIER DIE QUELLE einfügen]" target="_blank"> 
                Mehr erfahren </a> [HIER 3 Leere Zeilen] 
            <a href="[HIER DIE BILD-QUELLE einfügen]" target="_blank"> Bildquelle Pexels </a>',
        'post_status'   => 'publish',
        'post_author'   => 1,
        'comment_status' => 'closed',
        'ping_status' => 'closed', // Deaktiviert Pingbacks
    );

    Überlege dir einen geeigneten Titel. Der Blogpost soll minumum 100 wörter umfassen.
    PROMPT;

    $articleTitle = $articles[0]['title'];
    $articleDescription = $articles[0]['description'];
    $articleContent = $articles[0]['content'];
    $articleSource = $articles[0]['url'];

    try {
        $response = $client->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'gpt-4-0125-preview',
                'messages' => [
                    ['role'=> 'system', 'content'=> $systemPrompt],
                    ['role' => 'user', 'content' => 'Der Titel der Nachricht ist: ' . $articleTitle . 
                    ' Die Beschreibung ist: '. $articleDescription . 
                    'Ein Teil des Contents ist: '. $articleContent . 
                    'Die Quelle ist: ' . $articleSource .
                    'Die Bildquelle ist: ' . $imageURL
                    ],
                ],
            ],
        ]);

        $body = $response->getBody();
        $content = json_decode($body, true);

        return $content['choices'][0]['message']['content'];
    
    } catch (\Exception $e) {
        error_log('Error: ' . $e->getMessage());
        return null;
    }
}


function add_image($postId, $imageURL) {

    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    $attach_id = media_sideload_image($imageURL, $postId, null, 'id');

    if (!is_wp_error($attach_id) && is_numeric($attach_id)) {

        set_post_thumbnail($postId, $attach_id);

    } else {
        error_log('Fehler beim Hinzufügen des Bildes: ' . 
        (is_wp_error($attach_id) ? $attach_id->get_error_message() : 'Unbekannter Fehler'));
    }

}


add_filter('cron_schedules', 'add_weekly_cron_schedule');

function add_weekly_cron_schedule($schedules) {
    $schedules['weekly'] = array(
        'interval' => 604800,  // Anzahl der Sekunden in einer Woche
        'display' => __('Once Weekly')
    );
    return $schedules;
}

// Aktivieren des Plugins
function mein_plugin_aktivieren() {
    if (!wp_next_scheduled('mein_woechentlicher_cron_job')) {
        wp_schedule_event(time(), 'weekly', 'mein_woechentlicher_cron_job');
    }
}

// Deaktivieren des Plugins
function mein_plugin_deaktivieren() {
    $timestamp = wp_next_scheduled('mein_woechentlicher_cron_job');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'mein_woechentlicher_cron_job');
    }
}

add_action('mein_woechentlicher_cron_job', 'create_auto_post');

register_activation_hook(__FILE__, 'mein_plugin_aktivieren');
register_deactivation_hook(__FILE__, 'mein_plugin_deaktivieren');
