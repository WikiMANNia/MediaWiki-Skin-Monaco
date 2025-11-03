<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserOptionsLookup;

class SkinMonaco extends SkinTemplate {

	/**
	 * Overwrite few SkinTemplate methods which we don't need in Monaco
	 */
	public function buildSidebar(): array {
		return [];
	}

	/**
	 * @var Config
	 */
	private $config;

	private bool|User $mMastheadUser;
	private bool $mMastheadTitleVisible;
	private UserOptionsLookup $mUserOptionsLookup;
	private int $lastExtraIndex = 1000;

	public function __construct( array $options = [] ) {
		$this->config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'monaco' );
		$this->mUserOptionsLookup = $mUserOptionsLookup ?? MediaWikiServices::getInstance()->getUserOptionsLookup();

		parent::__construct( $options );
	}

	private static function getSkinMonacoFallbackTheme(): string {
		return "sapphire";
	}

	public static function getSkinMonacoThemeList(): array {
		return [ "beach", "brick", "carbon", "forest", "gaming", "jade", "moonlight", "obsession", "ruby", "sapphire", "sky", "slate", "smoke", "spring", "wima" ];
	}

	public static function getThemeKey(): string {
		return 'theme_monaco';
	}

	public function initPage( OutputPage $out ) {
		parent::initPage( $out );

		// ResourceLoader doesn't do ie specific styles that well iirc, so we have
		// to do those manually.
		$out->addStyle( 'Monaco/style/css/monaco_ie8.css', 'screen', 'IE 8' );
		$out->addStyle( 'Monaco/style/css/monaco_gteie8.css', 'screen', 'gte IE 8' );

		// Likewise the masthead is a conditional feature so it's hard to include
		// inside of the ResourceLoader.
		if ( $this->showMasthead() ) {
			$out->addStyle( 'Monaco/style/css/masthead.css', 'screen' );
		}

		$request = $this->getRequest();
		$theme_key = self::getThemeKey();
		$themes = self::getSkinMonacoThemeList();
		$user = RequestContext::getMain()->getUser();
		// Check the following things in this order:
		// 1) value of $wgMonacoTheme (set in site configuration)
		// 2) user's personal preference/override
		// 3) per-page usetheme URL parameter
		$theme_fallback = self::getSkinMonacoFallbackTheme();
		$theme_default = $this->config->get( 'MonacoTheme', $theme_fallback );
		if ( !in_array( $theme_default, $themes ) ) {
			// May be $wgMonacoTheme is not in the list (i.e. because a misspelling)
			$theme_default = $theme_fallback;
		}
		$theme = $theme_default;
		if ( $this->config->get( 'MonacoAllowUseTheme' ) ) {
			$theme_user = $this->mUserOptionsLookup->getOption( $user, $theme_key, $theme_default );
			if ( !in_array( $theme_user, $themes ) ) {
				$theme_user = $theme_default;
			}
			$theme = $request->getText( 'usetheme', $theme_user );
			if ( !in_array( $theme, $themes ) ) {
				$theme = $theme_user;
			}
		}

		// Theme is another conditional feature, we can't really resource load this
		$out->addStyle( "Monaco/style/{$theme}/css/main.css", 'screen' );

		// TODO: explicit RTL style sheets are supposed to be obsolete w/ResourceLoader
		// I have no way to test this currently, however. -haleyjd
		// rtl... hmm, how do we resource load this?
		$out->addStyle( 'Monaco/style/rtl.css', 'screen', '', 'rtl' );

		$out->addScript(
			'<!--[if IE]><script type="text/javascript' .
				'">\'abbr article aside audio canvas details figcaption figure ' .
				'footer header hgroup mark menu meter nav output progress section ' .
				'summary time video\'' .
				'.replace(/\w+/g,function(n){document.createElement(n)})</script><![endif]-->'
		);
	}

	public function getDefaultModules(): array {
		$modules = parent::getDefaultModules();

		return $modules;
	}

	public function showMasthead(): bool {
		if ( !$this->config->get( 'MonacoUseMasthead' ) ) {
			return false;
		}

		return is_bool( $this->getMastheadUser() ) ? false : true;
	}

	/**
	 * @return User
	 */
	public function getMastheadUser() {
		$title = $this->getTitle();

		if ( !isset( $this->mMastheadUser ) ) {
			if ( $title->inNamespace( NS_USER ) || $title->inNamespace( NS_USER_TALK ) ) {
				$this->mMastheadUser = User::newFromName( strtok( $title->getText(), '/' ), false );
				$this->mMastheadTitleVisible = false;
			} else {
				$this->mMastheadUser = false;
				// title is visible anyways if we're not on a masthead using page
				$this->mMastheadTitleVisible = true;
			}
		}

		return $this->mMastheadUser;
	}

	public function isMastheadTitleVisible(): bool {
		if ( !$this->showMasthead() ) {
			return true;
		}

		$this->getMastheadUser();

		return $this->mMastheadTitleVisible;
	}

	public function parseToolboxLinks( array $lines ): array {
		$nodes = [];
		if ( is_array( $lines ) ) {
			foreach ( $lines as $line ) {
				$trimmed = trim( $line, ' *' );
				# ignore empty lines
				if ( strlen( $trimmed ) == 0 ) {
					continue;
				}

				$item = MonacoSidebar::parseItem( $trimmed );

				$nodes[] = $item;
			}
		}

		return $nodes;
	}

	public function getLines( string $message_key ): array {
		$revisionStore = MediaWikiServices::getInstance()->getRevisionStore();
		$revision = $revisionStore->getRevisionByTitle( Title::newFromText( $message_key, NS_MEDIAWIKI ) );

		if ( is_object( $revision ) ) {
			$content = $revision->getContent( SlotRecord::MAIN );
			$text = ContentHandler::getContentText( $content );

			if ( trim( $text ) != '' ) {
				$temp = MonacoSidebar::getMessageAsArray( $message_key );
				if ( count( $temp ) > 0 ) {
					$lines = $temp;
				}
			}
		}

		if ( empty( $lines ) ) {
			$lines = MonacoSidebar::getMessageAsArray( $message_key );
		}

		return $lines;
	}

	public function getToolboxLinks(): array {
		return $this->parseToolboxLinks( $this->getLines( 'Monaco-toolbox' ) );
	}

	public function addExtraItemsToSidebarMenu( array &$node, array &$nodes ): void {
		$extraWords = [
			'#voted#' => [ 'highest_ratings', 'GetTopVotedArticles' ],
			'#popular#' => [ 'most_popular', 'GetMostPopularArticles' ],
			'#visited#' => [ 'most_visited', 'GetMostVisitedArticles' ],
			'#newlychanged#' => [ 'newly_changed', 'GetNewlyChangedArticles' ],
			'#topusers#' => [ 'community', 'GetTopFiveUsers' ]
		];

		if ( isset( $extraWords[ strtolower( $node['org'] ) ] ) ) {
			if ( substr( $node['org'], 0, 1 ) == '#' ) {
				if ( strtolower( $node['org'] ) == strtolower( $node['text'] ) ) {
					$node['text'] = wfMessage( trim( strtolower( $node['org'] ), ' *' ) )->text();
				}
				$node['magic'] = true;
			}

			$results = DataProvider::$extraWords[strtolower( $node['org'] )][1]();
			$results[] = [
				'url' => SpecialPage::getTitleFor( 'Top/' . $extraWords[ strtolower( $node['org'] ) ][0] )->getLocalURL(),
				'text' => strtolower( wfMessage( 'moredotdotdot' )->text() ), 'class' => 'Monaco-sidebar_more'
			];

			if ( $this->getUser()->isAllowed( 'editinterface' ) ) {
				if ( strtolower( $node['org'] ) == '#popular#' ) {
					$results[] = [
						'url' => Title::makeTitle( NS_MEDIAWIKI, 'Most popular articles' )->getLocalUrl(),
						'text' => wfMessage( 'monaco-edit-this-menu' )->text(), 'class' => 'Monaco-sidebar_edit'
					];
				}
			}

			foreach ( $results as $key => $val ) {
				$node['children'][] = $this->lastExtraIndex;
				$nodes[$this->lastExtraIndex]['text'] = $val['text'];
				$nodes[$this->lastExtraIndex]['href'] = $val['url'];

				if ( !empty( $val['class'] ) ) {
					$nodes[$this->lastExtraIndex]['class'] = $val['class'];
				}

				$this->lastExtraIndex++;
			}
		}
	}

	public function parseSidebarMenu( array $lines ): array {
		$nodes = [];
		$nodes[] = [];
		$lastDepth = 0;
		$i = 0;

		if ( is_array( $lines ) ) {
			foreach ( $lines as $line ) {
				# ignore empty lines
				if ( strlen( $line ) == 0 ) {
					continue;
				}

				$node = MonacoSidebar::parseItem( $line );
				$node['depth'] = strrpos( $line, '*' ) + 1;

				if ( $node['depth'] == $lastDepth ) {
					$node['parentIndex'] = $nodes[$i]['parentIndex'];
				} elseif ( $node['depth'] == $lastDepth + 1 ) {
					$node['parentIndex'] = $i;
				} else {
					for ( $x = $i; $x >= 0; $x-- ) {
						if ( $x == 0 ) {
							$node['parentIndex'] = 0;
							break;
						}

						if ( $nodes[$x]['depth'] == $node['depth'] - 1 ) {
							$node['parentIndex'] = $x;
							break;
						}
					}
				}

				if ( substr( $node['org'], 0, 1 ) == '#' ) {
					$this->addExtraItemsToSidebarMenu( $node, $nodes );
				}

				$nodes[$i + 1] = $node;
				$nodes[$node['parentIndex']]['children'][] = $i + 1;
				$lastDepth = $node['depth'];
				$i++;
			}
		}

		return $nodes;
	}

	public function getSidebarLinks(): array {
		return $this->parseSidebarMenu( $this->getLines( 'Monaco-sidebar' ) );
	}

	/**
	 * @return array|string|null
	 */
	public function getTransformedArticle( string $name, bool $asArray = false ) {
		$revisionStore = MediaWikiServices::getInstance()->getRevisionStore();
		$revision = $revisionStore->getRevisionByTitle( Title::newFromText( $name ) );
		$parser = MediaWikiServices::getInstance()->getParser();

		if ( is_object( $revision ) ) {
			$text = $revision->getText();

			if ( !empty( $text ) ) {
				$ret = $parser->transformMsg( $text, $parser->getOptions() );

				if ( $asArray ) {
					$ret = explode( "\n", $ret );
				}

				return $ret;
			}
		}

		return null;
	}
}
