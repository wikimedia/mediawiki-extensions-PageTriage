<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Extension\PageTriage\HookHandlers;

use MediaWiki\Extension\PageTriage\Hooks as PageTriageHooks;
use MediaWiki\Extension\PageTriage\PageTriageUtil;
use MediaWiki\Page\Hook\ArticleUndeleteHook;
use MediaWiki\Title\Title;

/**
 * @author Taavi Väänänen <hi@taavi.wtf>
 */
class UndeleteHookHandler implements ArticleUndeleteHook {
	/**
	 * @param Title $title
	 * @param bool $create
	 * @param string $comment
	 * @param int $oldPageId
	 * @param array $restoredPages
	 */
	public function onArticleUndelete( $title, $create, $comment, $oldPageId, $restoredPages ) {
		if ( !$create ) {
			// not interested in revdel actions
			return;
		}

		if ( !in_array( $title->getNamespace(), PageTriageUtil::getNamespaces() ) ) {
			// don't queue pages in namespaces where PageTriage is disabled
			return;
		}

		PageTriageHooks::addToPageTriageQueue( $title->getId(), $title );
	}
}
