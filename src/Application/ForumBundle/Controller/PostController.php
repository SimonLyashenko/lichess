<?php

namespace Application\ForumBundle\Controller;

use Bundle\ForumBundle\Controller\PostController as BasePostController;
use Bundle\ForumBundle\DAO\Topic;
use Bundle\ForumBundle\DAO\Post;

class PostController extends BasePostController
{
    public function newAction($topicId)
    {
        $topic = $this['forum.topic_repository']->findOneById($topicId);
        if (!$topic) {
            throw new NotFoundHttpException('The topic does not exist.');
        }

        $form = $this->createForm('forum_post_new', $topic);

        return $this->render('ForumBundle:Post:new.'.$this->getRenderer(), array(
            'form' => $form,
            'topic' => $topic
        ));
    }

    public function createAction($topicId)
    {
        $topic = $this['forum.topic_repository']->findOneById($topicId);
        if (!$topic) {
            throw new NotFoundHttpException('The topic does not exist.');
        }

        $form = $this->createForm('forum_post_new', $topic);
        $form->bind($this['request']->request->get($form->getName()));

        if(!$form->isValid()) {
            $lastPage = $this['templating.helper.forum']->getTopicNumPages($topic);
            return $this->forward('ForumBundle:Topic:show', array(
                'id' => $topicId
            ), array('page' => $lastPage));
        }

        $post = $form->getData();
        $this->savePost($post);

        $this['session']->setFlash('forum_post_create/success', true);
        $url = $this['templating.helper.forum']->urlForPost($post);

        return $this->redirect($url);
    }

}