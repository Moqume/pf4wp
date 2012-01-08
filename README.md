Plugin Framework for Wordpress (Pf4wp)
======================================

_Pf4wp_ is a framework to help develop plugins for [WordPress](http://www.wordpress.org).

Code the plugin, not decipher WordPress
---------------------------------------

While the use of the framework is not required to build a WordPress plugin, it will allow you to focus on the core plugin needs rather than that of WordPress. 

The framework deals with the nitty-gritty of WordPress, including actions, filters, deprecated functions, roles, capabilities and more, while you use the same code base of the framework to build your plugin. In turn, your turnaround time for developing a WordPress plugin is reduced!

Easer Maintainance
------------------

An additional benefit is consistency among plugins that use the framework, as any changes within the framework will be propragated to all plugins based on it. 

The framework also introduces optimized templating with the help of [Twig](http://twig.sensiolabs.org/), which brings it closer to a pure MVC framework. While it is not required to use any of the templating features, it will eliminate the need to hard code HTML, CSS or Javascript inside the plugins.

Library Interoprability
-----------------------

The framework follows the concepts of [Symfony2](http://symfony.com), a well established and rock solid MVC framework. This gives _Pf4wp_ the ability to use vendor (third party) libraries that use the PHP 5.3 namespace or PEAR naming conventions with ease.

_Pf4wp_ is released under the MIT License. Because of the flexible licensing terms of the framework, it can be incorporated in GPL as well as commercial products alike, and thus will work with most other libraries.

Getting Started
---------------

Download the .ZIP file or clone it into a `vendor` directory for the plugin, commonly `wp-contents/plugin/my-plugin/vendor/pf4wp/`.

If you are not familiar with the framework yet and you are running a Linux operating system, consider running `/vendor/pf4wp/bin/gendoc`, which will install [DocBlox](www.docblox-project.org/) and in turn generate the help documentation. 

Alternatively, you can run any other document generator supporting the PHPDoc notations.

License
-------

Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to dealin the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.