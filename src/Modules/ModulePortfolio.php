<?php

declare(strict_types=1);

/*
 * Contao Portfolio Bundle for Contao Open Source CMS.
 * @copyright  Copyright (c) 2020, Erdmann & Freunde
 * @author     Erdmann & Freunde <https://erdmann-freunde.de>
 * @license    MIT
 * @link       http://github.com/erdmannfreunde/contao-portfolio-bundle
 */

namespace EuF\PortfolioBundle\Modules;

use Contao\ContentModel;
use Contao\Date;
use Contao\FilesModel;
use Contao\FrontendUser;
use Contao\StringUtil;
use EuF\PortfolioBundle\Models\PortfolioArchiveModel;
use EuF\PortfolioBundle\Models\PortfolioCategoryModel;
use EuF\PortfolioBundle\Classes\Portfolio;

/**
 * Class ModulePortfolio.
 *
 * Parent class for portfolio modules.
 */
abstract class ModulePortfolio extends \Module
{
    /**
     * Sort out protected archives
     *
     * @param array $arrArchives
     *
     * @return array
     */
    protected function sortOutProtected(array $arrArchives): array
    {
        if (empty($arrArchives) || !\is_array($arrArchives))
        {
            return $arrArchives;
        }

        $this->import(FrontendUser::class, 'User');
        $objArchive = PortfolioArchiveModel::findMultipleByIds($arrArchives);
        $arrArchives = array();

        if ($objArchive !== null)
        {
            while ($objArchive->next())
            {
                if ($objArchive->protected)
                {
                    if (!FE_USER_LOGGED_IN || !\is_array($this->User->groups))
                    {
                        continue;
                    }

                    $groups = StringUtil::deserialize($objArchive->groups);

                    if (empty($groups) || !\is_array($groups) || !\count(array_intersect($groups, $this->User->groups)))
                    {
                        continue;
                    }
                }

                $arrArchives[] = $objArchive->id;
            }
        }

        return $arrArchives;
    }

    /**
     * Parse an item and return it as string.
     *
     * @param mixed $objItem
     * @param bool $blnAddArchive
     * @param mixed $strClass
     * @param mixed $intCount
     *
     * @return string
     * @throws \Exception
     */
    protected function parseItem($objItem, $blnAddArchive = false, $strClass = '', $intCount = 0): string
    {
        global $objPage;

        $objTemplate = new \FrontendTemplate($this->portfolio_template);
        $objTemplate->setData($objItem->row());

        $objTemplate->class = (('' !== $objItem->cssClass) ? ' ' . $objItem->cssClass : '') . $strClass;
        $objTemplate->headline = $objItem->headline;
        $objTemplate->linkHeadline = $this->generateLink($objItem->headline, $objItem, $blnAddArchive);
        $objTemplate->more = $this->generateLink($GLOBALS['TL_LANG']['MSC']['more'], $objItem, $blnAddArchive, true);
        $objTemplate->link = Portfolio::generatePortfolioUrl($objItem, $blnAddArchive);
        $objTemplate->count = $intCount; // see #5708
        $objTemplate->text = '';
        $objTemplate->hasText = false;
        $objTemplate->hasTeaser = false;

        // Clean the RTE output
        if ($objItem->teaser) {
            $objTemplate->hasTeaser = true;
            $objTemplate->teaser = StringUtil::toHtml5($objItem->teaser);
            $objTemplate->teaser = StringUtil::encodeEmail($objTemplate->teaser);
        }

        // Display the "read more" button for external/article links
        if ('default' !== $objItem->source) {
            $objTemplate->text = true;
        } // Compile the portfolio text
        else {
            $objElement = ContentModel::findPublishedByPidAndTable($objItem->id, 'tl_portfolio');
            if (null !== $objElement) {
                while ($objElement->next()) {
                    $objTemplate->text .= self::getContentElement($objElement->current());
                }
            }

            $objTemplate->hasText = static function () use ($objItem)
            {
                return ContentModel::countPublishedByPidAndTable($objItem->id, 'tl_portfolio') > 0;
            };
        }

        // Add the meta information
        $objTemplate->date = Date::parse($objPage->dateFormat, $objItem->date);
        $objTemplate->timestamp = $objItem->date;

        if ($objItem->categories) {
            $objTemplate->categories = '';
            $categories = StringUtil::deserialize($objItem->categories);
            foreach ($categories as $category) {
                $objPortfolioCategoryModel = PortfolioCategoryModel::findByPk($category);
                $objCategories[] = $objPortfolioCategoryModel->alias;
                if (!$objTemplate->category_titles) {
                    $objTemplate->category_titles = '<ul class="level_1"><li>' . $objPortfolioCategoryModel->title . '</li>';
                } else {
                    $objTemplate->category_titles .= '<li>' . $objPortfolioCategoryModel->title . '</li>';
                }
            }
            $objTemplate->category_titles .= '</ul>';
            $objTemplate->categories .= implode(',', $objCategories);
        }

        $objTemplate->addImage = false;

        // Add an image
        if ($objItem->addImage && '' !== $objItem->singleSRC) {
            $objModel = FilesModel::findByUuid($objItem->singleSRC);

            if (null !== $objModel && is_file(TL_ROOT . '/' . $objModel->path)) {
                // Do not override the field now that we have a model registry (see #6303)
                $arrArticle = $objItem->row();

                // Override the default image size
                if ('' !== $this->imgSize) {
                    $size = StringUtil::deserialize($this->imgSize);

                    if ($size[0] > 0 || $size[1] > 0 || is_numeric($size[2])) {
                        $arrArticle['size'] = $this->imgSize;
                    }
                }

                $arrArticle['singleSRC'] = $objModel->path;
                $this->addImageToTemplate($objTemplate, $arrArticle, null, null, $objModel);

                // Link to the portfolio reader if no image link has been defined (see #30)
                if (!$objTemplate->fullsize && !$objTemplate->imageUrl && $objTemplate->text) {
                    // Unset the image title attribute
                    $picture = $objTemplate->picture;
                    unset($picture['title']);
                    $objTemplate->picture = $picture;

                    // Link to the portfolio reader
                    $objTemplate->href = $objTemplate->link;
                    $objTemplate->linkTitle = StringUtil::specialchars(sprintf($GLOBALS['TL_LANG']['MSC']['readMore'], $objItem->headline), true);

                    // If the external link is opened in a new window, open the image link in a new window, too
                    if ('external' === $objTemplate->source && $objTemplate->target && false === strpos($objTemplate->attributes, 'target="_blank"')) {
                        $objTemplate->attributes .= ' target="_blank"';
                    }
                }
            }
        }

        return $objTemplate->parse();
    }

    /**
     * Parse one or more items and return them as array
     *
     * @param array $arrItems
     * @param boolean $blnAddArchive
     *
     * @return array
     * @throws \Exception
     */
    protected function parseItems(array $arrItems, $blnAddArchive = false): array
    {
        $limit = count($arrItems);

        if ($limit < 1) {
            return [];
        }

        $count = 0;
        $arrArticles = [];
        $uuids = [];

        foreach ($arrItems as $objArticle) {
            if ($objArticle->addImage && $objArticle->singleSRC) {
                $uuids[] = $objArticle->singleSRC;
            }
        }

        // Preload all images in one query so they are loaded into the model registry
        FilesModel::findMultipleByUuids($uuids);

        foreach ($arrItems as $objArticle) {
            $arrArticles[] = $this->parseItem($objArticle, $blnAddArchive, ((++$count === 1) ? ' first' : '') . (($count === $limit) ? ' last' : '') . (($count % 2 === 0) ? ' odd' : ' even'), $count);
        }

        return $arrArticles;
    }

    /**
     * Generate a link and return it as string.
     *
     * @param mixed $strLink
     * @param mixed $objItem
     * @param mixed $blnAddArchive
     * @param mixed $blnIsReadMore
     *
     * @return string
     * @throws \Exception
     */
    protected function generateLink($strLink, $objItem, $blnAddArchive = false, $blnIsReadMore = false): string
    {
        // Internal link
        if ('external' !== $objItem->source) {
            return sprintf('<a href="%s" title="%s">%s%s</a>',
                Portfolio::generatePortfolioUrl($objItem, $blnAddArchive),
                StringUtil::specialchars(sprintf($GLOBALS['TL_LANG']['MSC']['readMore'], $objItem->headline), true),
                $strLink,
                ($blnIsReadMore ? ' <span class="invisible">' . $objItem->headline . '</span>' : ''));
        }

        // Ampersand URIs
        $strArticleUrl = ampersand($objItem->url);

        global $objPage;

        $attributes = '';
        if ($objItem->target) {
            $attributes = (('xhtml' === $objPage->outputFormat) ? ' onclick="return !window.open(this.href)"' : ' target="_blank"');
        }

        // External link
        return sprintf('<a href="%s" title="%s"%s>%s</a>',
            $strArticleUrl,
            StringUtil::specialchars(sprintf($GLOBALS['TL_LANG']['MSC']['open'], $strArticleUrl)),
            $attributes,
            $strLink);
    }
}
