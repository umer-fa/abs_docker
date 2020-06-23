let requeueMailModal = $("div#requeueMailModal");
let requeueModalEmId = $("input#requeueModalEmId");
let requeueModalEmSub = $(".requeueModalEmSub");
let requeueModalEmAddr = $(".requeueModalEmAddr");

$(function () {
    $("[data-read-mail]").on("click", function () {
        let mailId = parseInt($(this).data("read-mail"));
        if (mailId > 0) {
            readMailPopup(mailId);
        }
    });

    $("[data-requeue-mail]").on("click", function () {
        resetRequeueModal();
        let mailId = parseInt($(this).data("requeue-mail"));
        if (mailId > 0) {
            let mailEmSub = $(this).data("em-subject");
            let mailEmAddr = $(this).data("em-addr");
            if (mailEmSub.length && mailEmAddr.length) {
                showRequeueModal(mailId, mailEmAddr, mailEmSub);
            }
        }
    });
});

function resetRequeueModal() {
    $(requeueModalEmId).val("");
    $(requeueModalEmSub).text("");
    $(requeueModalEmAddr).text("");
}

function showRequeueModal(mailId, email, subject) {
    $(requeueModalEmId).val(mailId);
    $(requeueModalEmSub).text(subject);
    $(requeueModalEmAddr).text(email);
    $(requeueMailModal).modal("show");
}

function readMailPopup(mailId) {
    let mailPopupName = "queuedMail_" + mailId;
    window.open(rootPath + "/mails/queue?read&mail=" + mailId, mailPopupName, "width=540,height=640,toolbar=0,menubar=0,location=0");
}
