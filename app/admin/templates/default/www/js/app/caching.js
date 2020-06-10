$(function () {
    $("[data-del-cached]").on("click", function () {
        let key = $(this).attr("data-del-cached");
        if (key && key.length) {
            deleteCacheKey(key, this);
        }
    });
});

function deleteCacheKey(key, trigger) {
    xhrCall(rootPath + '/app/caching?cached', {key: key}, {type: "delete"}, function (result) {
        xhrResult(result);
        if (result.hasOwnProperty("status") && result["status"] === true) {
            let cachedItemRow = $(trigger).closest("tr");
            $(cachedItemRow).remove();
        }
    });
}
