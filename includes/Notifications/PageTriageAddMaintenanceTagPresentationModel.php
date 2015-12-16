<?php

class PageTriageAddMaintenanceTagPresentationModel extends EchoEventPresentationModel {
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

        // parent adds agent & gender, this adds title as 3rd param, tag list as
        // 4th & amount of tags as 5th (for PLURAL)
        $msg->params( $this->event->getTitle() );
        $msg->params( $this->getTagsForOutput() );

        return $msg;
    }

    /**
     * Returns an array of [tag list, amount of tags], to be used as msg params.
     *
     * @return array [(string) tag list, (int) amount of tags]
     */
    protected function getTagsForOutput() {
        $eventData = $this->event->getExtra();

        if ( !is_array( $eventData ) ) {
            return array( '', 0 );
        }

        return array( $this->language->listToText( $eventData ), count( $eventData ) );
    }
}
