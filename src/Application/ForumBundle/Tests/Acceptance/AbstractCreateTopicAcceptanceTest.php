<?php

namespace Application\ForumBundle\Tests\Acceptance;

abstract class AbstractCreateTopicAcceptanceTest extends AbstractAcceptanceTest
{
    public function testClickOnCreateTopicLinkGoesToTheFormPage()
    {
        $client = $this->createClient();
        $crawler = $this->requestCategoryPage($client);
        $crawler = $client->click($crawler->selectLink('Create a new topic')->link());
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertEquals('New topic', $crawler->filter('#lichess_forum h1')->text());
    }

    public function testTopicCreationPageShowsAForm()
    {
        $client = $this->createClient();
        $crawler = $this->requestTopicCreationPage($client);
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertEquals(1, $crawler->filter('#lichess_forum form')->count());
        $this->assertEquals('Create the topic', $crawler->filter('#lichess_forum .submit')->text());
    }

    public function testSubmitEmptyForm()
    {
        $client = $this->createClient();
        $crawler = $this->requestTopicCreationPage($client);
        $form = $crawler->selectButton('Create the topic')->form();
        $client->submit($form);
        $this->assertFalse($client->getResponse()->isRedirect());
        $this->assertEquals(1, $crawler->filter('#lichess_forum form')->count());
    }

    public function testSubmitValidForm()
    {
        $topicSubject = uniqid();
        $topicMessage = 'new topic message';
        $client = $this->createClient();
        $crawler = $this->requestTopicCreationPage($client);
        $form = $crawler->selectButton('Create the topic')->form();
        $form['forum_new_topic_form[category]'] = $crawler->filter('#forum_new_topic_form_category option')->first()->attr('value');
        $form['forum_new_topic_form[subject]'] = $topicSubject;
        $form['forum_new_topic_form[firstPost][message]'] = $topicMessage;
        $client->submit($form);
        $this->assertTrue($client->getResponse()->isRedirect());
        $crawler = $client->followRedirect();
        $this->assertFalse($client->getResponse()->isRedirect());
        $this->assertEquals($topicSubject, $crawler->filter('#lichess_forum h1')->text());
        $this->assertRegexp('/'.$topicMessage.'/', $client->getResponse()->getContent());

        $client->getContainer()->get('doctrine.odm.mongodb.document_manager')->clear();
        $topic = $client->getContainer()->get('forum.repository.topic')->findOneBySlug($topicSubject);
        $client->getContainer()->get('forum.remover.topic')->remove($topic);
        $client->getContainer()->get('doctrine.odm.mongodb.document_manager')->flush();
    }

    protected function requestCategoryPage($client)
    {
        $url = $this->generateUrl($client, 'forum_category_show', array('slug' => 'general-chess-discussion'));

        return $client->request('GET', $url);
    }

    protected function requestTopicCreationPage($client)
    {
        $url = $this->generateUrl($client, 'forum_category_topic_new', array('slug' => 'general-chess-discussion'));

        return $client->request('GET', $url);
    }
}