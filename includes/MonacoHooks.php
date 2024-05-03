<?php

use MediaWiki\Hook\OutputPageBodyAttributesHook;

class MonacoHooks implements
	OutputPageBodyAttributesHook
{
	/**
	 * @param OutputPage $out OutputPage which called the hook, can be used to get the real title
	 * @param Skin $sk Skin that called OutputPage::headElement
	 * @param string[] &$bodyAttrs Array of attributes for the body tag passed to Html::openElement
	 */
	public function onOutputPageBodyAttributes( $out, $skin, &$bodyAttrs ): void {
		if ( $skin->getSkinName() !== 'monaco' ) {
			return;
		}

		$bodyAttrs['class'] .= ' color2';
		
		$action = $skin->getRequest()->getVal( 'action' );
		if ( in_array( $action, [ 'edit', 'history', 'diff', 'delete', 'protect', 'unprotect', 'submit' ] ) ) {
			$bodyAttrs['class'] .= ' action_' . $action;
		} elseif ( empty( $action ) || in_array( $action, [ 'view', 'purge' ] ) ) {
			$bodyAttrs['class'] .= ' action_view';
		}
		
		if ( $skin->showMasthead() ) {
			if ( $skin->isMastheadTitleVisible() ) {
			$bodyAttrs['class'] .= ' masthead-special';
			} else {
				$bodyAttrs['class'] .= ' masthead-regular';
			}
		}
		
		$bodyAttrs['id'] = 'body';

		if ( !$skin->getUser()->isRegistered() ) {
			$bodyAttrs['class'] .= ' loggedout';
		}

		if ( $out->getTitle()->isMainPage() ) {
			$bodyAttrs['class'] .= ' mainpage';
		}
	}
}

