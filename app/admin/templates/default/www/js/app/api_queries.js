$(function () {
    $("[data-load-api-query]").on("click", function () {
        let rayId = $(this).attr("data-load-api-query");
        let xsrfToken = $(this).attr("data-xsrf");
        loadQueryRay(rayId, xsrfToken);
    });
});

let apiQueryModal = $("div#apiQueryModal");

function resetApiQueryModal() {
    $(apiQueryModal).modal('hide');

    let resCodeTop = $(apiQueryModal).find("#resCodeTop");
    $(resCodeTop).removeClass("text-warning text-success");
    $(resCodeTop).find("span").text("???");
}

function loadQueryRay(id, xsrf) {
    resetApiQueryModal();
    xhrCall(rootPath + '/api/queries?query', {xsrf: xsrf, id: id}, {type: "get"}, function (result) {
        xhrResult(result);
        if (result.hasOwnProperty("status") && result["status"] === true) {
            if (result.hasOwnProperty("query") && typeof result["query"] === "object") {
                let query = result["query"];

                // Populate fields
                $.each(query, function (key, value) {
                    let paramElements = $(apiQueryModal).find('[data-api-query-param="' + key + '"]');
                    if ($(paramElements).length) {
                        $.each(paramElements, function (index, paramElem) {
                            if ($(paramElem).attr("data-param-feed") === "val") {
                                $(paramElem).val(value);
                            } else {
                                $(paramElem).text(value);
                            }
                        });
                    }
                });

                // Response code top
                if (query["resCode"] > 0) {
                    let resCodeTop = $(apiQueryModal).find("#resCodeTop");
                    $(resCodeTop).find("span").text(query["resCode"]);
                    if (query["resCode"] === 200) {
                        $(resCodeTop).addClass("text-success");
                    } else {
                        $(resCodeTop).addClass("text-warning");
                    }
                }

                $(apiQueryModal).modal('show');
            }
        }
    });
}
