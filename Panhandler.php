<?php

 /* Panhandler.php --- An interface for fetching product information online */

 /* Copyright (C) 2010 Cyber Sprocket Labs <info@cybersprocket.com>         */

 /* Authors: Eric James Michael Ritz <Eric@cybersprocket.com>               */

 /* This program is free software; you can redistribute it and/or           */
 /* modify it under the terms of the GNU General Public License             */
 /* as published by the Free Software Foundation; either version 3          */
 /* of the License, or (at your option) any later version.                  */

 /* This program is distributed in the hope that it will be useful,         */
 /* but WITHOUT ANY WARRANTY; without even the implied warranty of          */
 /* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           */
 /* GNU General Public License for more details.                            */

 /* You should have received a copy of the GNU General Public License       */
 /* along with this program. If not, see <http://www.gnu.org/licenses/>.    */

/**
 * Overview
 *
 * This file provides a common interface for getting product
 * information from popular online sources such as eBay, Amazon,
 * Comission Junction, and so on.  Classes which implement this
 * interface are referred to as 'drivers' in the comments below.
 */

/**
 * The Panhandles interface represents products as objects of this
 * class, which is nothing more than a simple container for common
 * pieces of data that we run across.
 */
final class PanhandlerProduct {
    public $name;
    public $description;
    public $price;
    public $web_urls;
    public $image_urls;
}

/**
 * All drivers need to implement this.
 */
interface Panhandles {

    /**
     * Accepts keywords as an array of strings, and returns an array
     * of PanhandlerProduct objects representing all of the products
     * matching those keywords.
     */
    public function get_products_by_keywords($keywords);

}

?>