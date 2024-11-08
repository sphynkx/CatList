[Mediawiki](https://www.mediawiki.org/) extension for [Veda.Wiki](https://veda.wiki/) project (extension is project specific, works only with project's templates). This extension for displaying a list of category pages in the form of tables with photos, names and synonyms. Written to replace the previous implementation based on [DPL](https://www.mediawiki.org/wiki/Extension:DynamicPageList3) and a template system. During the update of the engine and extensions, DPL proved to be unstable and capricious - the syntax changed, sorting was lost, and the DPL modification of the previous version stopped working.

The extension is connected to the page by setting the tags `<catlist></catlist>`. The text between the tags is considered the category name and the pages of the main namespace belonging to this category are displayed. In the absence of text between the tags, the extension considers the name of the current page to be the category name.

The extension displays a list of pages designed with different templates for the infobox, but you can configure the output to be limited to specific ones - see the templates parameter.

The number of elements displayed is not controlled (although it is technically possible); all pages of the category are displayed on a single page.

Parameters in the opening tag:

* __`caption`__ - Setting an alternative section title. After the `=` sign, the desired title text is specified in quotation marks. You can put a "template" __\_\_cat\_\___ in the title text, which will substitute a wiki link to the category. For example:
 
    `<catlist caption="My caption for {{cat}}:">Guatemala</catlist>`

    You can also use wiki formatting, but be careful. If you use font formatting (single quotes), make sure that the modifier content is enclosed in double quotes. Do not use HTML tag formatting - it causes rendering failure. If you really need to use HTML tags, then you need to replace the `<` and `>` symbols with `&lt;` and `&gt;` respectively. But no guarantees. It was not possible to make such a replacement at the extension code stage - probably an engine bug.

* __`gotop`__ - Connects the `GoTop` template for the "button" to return to the top of the page. It will appear at the bottom left. The parameter is specified without modifiers.

* __`namespaces`__ - A list of namespace numbers, separated by commas (the correspondence between numbers and namespace names can be found by temporarily inserting the shownamespaces parameter). Specified after the `=` sign in quotation marks. For example:

    `<catlist namespaces="0,3000">Guatemala</catlist>`

* __`shownamespaces`__ - Outputs a list of all namespaces - numbers and corresponding names. Cancels the output of the page list. Does not require modifiers. Example:

  `<catlist shownamespaces></catlist>`

    Result:

      Array (
      
        [-2] => медиа
        [-1] => служебная
        [0] => 
        [1] => обсуждение
        [2] => участница
        [3] => обсуждение_участницы
        [4] => veda_wiki
        ...
      )
  
* __`templates`__ - List of template names, pages with which will be output. Specified after the `=` sign in quotation marks, infobox names are separated by commas. For example:

    `<catlist templates="Организация,Персона">Гватемала</catlist>`

    For pages without an infobox, it displays a plate with a default image and page name. You can make a "fake" infobox - add template markup to the page, specify the *Изображение*, *Синоним*, *НатСиноним* parameters and fill them in. As a template name, put any word that you will then specify in the tag parameter. You can use `empty` which is an empty insert template. After all the required parameters, specify `|` on a new line, since inside the extension, the search for parameters occurs in the areas between the `|` symbols. Example:

      {{empty
      |Изображение = 1001-photo 2022-11-02 14-03-20 (3).jpg
      |Синонимы = Синоним
      |НатСинонимы=Натур.Синоним
      |
      }}

* __`toc`__ - Enables the Alphanumeric Index mode. At the top, a table of contents is generated from the first characters of the name of each found page, with links to sections. The table of contents is wrapped in a hidden block. The list of pages is divided into subgroups by capital letters, and the capital letter is specified as the section name. A parameter without a modifier generates a hidden block in the expanded state. To make the initial state collapsed, specify the `collapsed` modifier after the `=` sign.
