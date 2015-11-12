<?php

class PageTriageMarkAsReviewedPresentationModel extends EchoEventPresentationModel {
    /**
     * {@inheritdoc}
     */
    public function canRender() {
        return $this->event->getTitle() instanceof Title;
    }

    /**
     * {@inheritdoc}
     */
    public function getIconType() {
        return 'checkmark';
    }

    /**
     * {@inheritdoc}
     */
    public function getPrimaryLink() {
        return array(
            $this->event->getTitle()->getFullURL(),
            $this->msg( 'notification-link-text-view-page' )->text()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaderMessage() {
        $msg = parent::getHeaderMessage();

        // parent adds agent & gender, this adds title as 3rd param
        $msg->params( $this->event->getTitle() );

        return $msg;
    }
}
