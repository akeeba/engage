/*
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

if (typeof akeeba == "undefined")
{
    var akeeba = {};
}

if (typeof akeeba.Engage == "undefined")
{
    akeeba.Engage = {};
}

if (typeof akeeba.Engage.Comments == "undefined")
{
    akeeba.Engage.Comments = {
        "onEditButton": null
    };
}

akeeba.Engage.Comments.onEditButton = function (e)
{
    e.preventDefault();

    var clickedElement = null;

    if (typeof e.target === "object")
    {
        clickedElement = e.target;
    }
    else if (typeof e.srcElement === "object")
    {
        clickedElement = e.srcElement;
    }

    if (clickedElement === null)
    {
        return false;
    }

    /**
     * Construct the edit URL for the comment. The akeeba.Engage.Comments.editURL script option key comes from the
     * components/com_engage\View\Comments\Html.php file.
     */
    var id     = akeeba.System.data.get(clickedElement, "data-akengageid", "0");
    var newURL = akeeba.System.getOptions("akeeba.Engage.Comments.editURL") + encodeURIComponent(id);

    if ((typeof part === "undefined") || (part !== ""))
    {
        newURL += "&part=" + part
    }

    window.location = newURL;
};

akeeba.Engage.Comments.onReplyButton = function (e)
{
    e.preventDefault();

    var clickedElement = null;

    if (typeof e.target === "object")
    {
        clickedElement = e.target;
    }
    else if (typeof e.srcElement === "object")
    {
        clickedElement = e.srcElement;
    }

    if (clickedElement === null)
    {
        return false;
    }

    var parentId      = akeeba.System.data.get(clickedElement, "akengageid", 0) * 1;
    var inReplyToName = akeeba.System.data.get(clickedElement, "akengagereplyto", 0);
    var form          = document.forms["akengageCommentForm"];
    var wrapper       = document.getElementById("akengage-comment-inreplyto-wrapper");

    form.parent_id.value  = parentId;
    wrapper.style.display = "none";

    if (parentId !== 0)
    {
        var inReplyTo       = document.getElementById("akengage-comment-inreplyto-name");
        inReplyTo.innerText = inReplyToName;

        wrapper.style.display = "block";
    }

    document.location.hash = "";
    document.location.hash = "#akengageCommentForm";
};

akeeba.Engage.Comments.onCancelReplyButton = function (e)
{
    e.preventDefault();

    var form      = document.forms["akengageCommentForm"];
    var wrapper   = document.getElementById("akengage-comment-inreplyto-wrapper");
    var inReplyTo = document.getElementById("akengage-comment-inreplyto-name");

    form.parent_id.value  = 0;
    wrapper.style.display = "none";
    inReplyTo.innerText   = "";
};


akeeba.System.documentReady(function ()
{
    akeeba.System.iterateNodes("button.akengage-comment-edit-btn", function (elButton)
    {
        akeeba.System.addEventListener(elButton, "click", akeeba.Engage.Comments.onEditButton);
    });

    akeeba.System.iterateNodes("button.akengage-comment-reply-btn", function (elButton)
    {
        akeeba.System.addEventListener(elButton, "click", akeeba.Engage.Comments.onReplyButton);
    });

    akeeba.System.addEventListener("akengage-comment-inreplyto-cancel", "click", akeeba.Engage.Comments.onCancelReplyButton);
});