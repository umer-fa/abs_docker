let sessArchiveModal = $("div#sessArchiveModal");
let sessArchiveId = $("input#sessArchiveId");

$(function () {
    $("[data-sess-archive]").on("click", function () {
        let sessionId = parseInt($(this).data("sess-archive"));
        if (sessionId > 0) {
            archiveSessionModal(sessionId);
        }
    });
});

function archiveSessionModal(sessId) {
    $(sessArchiveId).val(sessId);
    $(sessArchiveModal).modal("show");
}
