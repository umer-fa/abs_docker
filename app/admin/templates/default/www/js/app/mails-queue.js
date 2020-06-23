$(function () {
    $("[data-read-mail]").on("click", function () {
        let mailId = parseInt($(this).data("read-mail"));
        if (mailId > 0) {
            readMailPopup(mailId);
        }
    });
});

function readMailPopup(mailId) {
    let mailPopupName = "queuedMail_" + mailId;
    window.open(rootPath + "/mails/queue?read&mail=" + mailId, mailPopupName, "width=480,height=550,toolbar=0,menubar=0,location=0");
}
