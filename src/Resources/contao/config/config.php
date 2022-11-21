<?php

declare(strict_types=1);

/*
 * Contao Portfolio Bundle for Contao Open Source CMS.
 * @copyright  Copyright (c) Erdmann & Freunde
 * @author     Erdmann & Freunde <https://erdmann-freunde.de>
 * @license    MIT
 * @link       http://github.com/erdmannfreunde/contao-portfolio-bundle
 */

array_insert($GLOBALS['BE_MOD']['content'], 2, [
    'portfolio' => [
        'tables' => ['tl_portfolio_archive', 'tl_portfolio', 'tl_portfolio_category', 'tl_content'],
        //'icon'        => 'system/modules/portfolio/assets/icon.png',
    ],
]);

/*
 * Front end modules
 */
array_insert($GLOBALS['FE_MOD'], 3, [
    'portfolio' => [
        'portfoliolist' => '\\EuF\\PortfolioBundle\\Modules\\ModulePortfolioList',
        'portfolioreader' => '\\EuF\\PortfolioBundle\\Modules\\ModulePortfolioReader',
    ],
]);

$GLOBALS['TL_MODELS']['tl_portfolio'] = '\\EuF\\PortfolioBundle\\Models\\PortfolioModel';
$GLOBALS['TL_MODELS']['tl_portfolio_archive'] = '\\EuF\\PortfolioBundle\\Models\\PortfolioArchiveModel';
$GLOBALS['TL_MODELS']['tl_portfolio_category'] = '\\EuF\\PortfolioBundle\\Models\\PortfolioCategoryModel';

/*
 * Register hooks
 */
$GLOBALS['TL_HOOKS']['getSearchablePages'][] = ['\\EuF\\PortfolioBundle\\Classes\\Portfolio', 'getSearchablePages'];

/*
 * Add permissions
 */
$GLOBALS['TL_PERMISSIONS'][] = 'portfolio';
$GLOBALS['TL_PERMISSIONS'][] = 'portfoliop';
