<?php
namespace Dumpert\ImportData;

use Goutte\Client;

final class importData
{

    public function initialize()
    {
        // Load models
        $this->loadModel('Videos');
        $this->loadModel('Users');
        $this->loadModel('Comments');

        parent::initialize();
    }

    public function run()
    {
        for ($i = 1; $i < 100000; $i++) {
            $this->getPage($i);
            Log::info("Running go to [Page={$i}]");
            sleep(rand(15, 25));
        }

    }

    public function getPage($counter)
    {
        $client = new Client();
        $jar = new \GuzzleHttp\Cookie\CookieJar();
        $dumpertUrl = "http://www.dumpert.nl/" . $counter . "/";
        $crawler = $client->request('GET', $dumpertUrl, ['cookies' => $jar]);
        $links = $crawler->filter('.dumpthumb')->each(function ($node) {
            return $node->attr('href');
        });

        foreach ($links as $link) {

            try {
                $this->getVideoPage($link);
            } catch (\Exception $e) {
                Log::error("[Counter={$counter}] [Error = {$e->getMessage()}]");
            }
            Log::info("Running go to [Link={$link}]");
            sleep(rand(1, 3));
        }
    }

    public function getVideoPage($url)
    {
        $videoHash = md5($url);
        $hashVideo = $this->Videos->find()->where(['hash' => $videoHash]);

        if ($hashVideo->count() != 0) {
            Log::alert("hash is already in DB [URL={$url}]");

            return;
        }

        $client = new Client();
        $crawler = $client->request('GET', $url);


        $video = $this->Videos->newEntity();
        $video->url = $url;
        $video->hash = $videoHash;
        $video->title = $crawler->filter(".dump-desc > h1")->text();
        $video->date = $crawler->filter(".dump-pub")->text();
        $video->kudos = $crawler->filter(".dump-kudos")->filter(".dump-amt")->text();
        $video->active = 1;
        $video = $this->Videos->save($video);
        Log::info("Saved video [Hash={$videoHash}] [Id={$video->id}]");
        $commentsUrl = $this->getCommentUrl($url);
        $crawler = $client->request('GET', $commentsUrl);

        // if comments stil active do not svae commnets
        $active = $crawler->filter(".active");
        if ($active->count() != 0) {
            Log::info("Has active commnets yet save later [Id={$video->id}]");
            $video->active = 1;
            $this->Videos->save($video);

            return;
        }

        $comments = $crawler->filter('.comment')->each(function ($node) {
            return [
                "text" => $node->filter("p")->text(),
                "votes" => $node->filter(".commentkudocount")->text(),
                "userName" => $node->filter(".username")->text(),
                "date" => $node->filter(".datetime")->text()
            ];
        });


        foreach ($comments as $comment) {
            $commentEntity = $this->Comments->newEntity();
            $commentEntity->text = $comment['text'];
            $commentEntity->video_id = $video->id;
            $commentEntity->date = $comment['date'];
            $commentEntity->votes = $comment['votes'];

            //save user
            $user = $this->Users->findOrCreate(['name' => $comment['userName']]);
            $user = $this->Users->save($user);
            $commentEntity->user_id = $user->id;
            $this->Comments->save($commentEntity);
        }

        $countComments = count($comments);

        Log::info("Found [Count={$countComments}] comments for [Id={$video->id}]");

        $video->comments = $countComments;
        $this->Videos->save($video);


    }

    public function getCommentUrl($url)
    {
        $url = explode("/", $url);

        return "https://comments.dumpert.nl/embed/" . $url[4] . "/" . $url[5] . "/comments/";
    }

}