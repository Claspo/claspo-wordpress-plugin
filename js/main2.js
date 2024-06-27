(function polyfill() {
  const relList = document.createElement("link").relList;
  if (relList && relList.supports && relList.supports("modulepreload")) {
    return;
  }
  for (const link of document.querySelectorAll('link[rel="modulepreload"]')) {
    processPreload(link);
  }
  new MutationObserver((mutations) => {
    for (const mutation of mutations) {
      if (mutation.type !== "childList") {
        continue;
      }
      for (const node of mutation.addedNodes) {
        if (node.tagName === "LINK" && node.rel === "modulepreload")
          processPreload(node);
      }
    }
  }).observe(document, { childList: true, subtree: true });
  function getFetchOpts(link) {
    const fetchOpts = {};
    if (link.integrity)
      fetchOpts.integrity = link.integrity;
    if (link.referrerPolicy)
      fetchOpts.referrerPolicy = link.referrerPolicy;
    if (link.crossOrigin === "use-credentials")
      fetchOpts.credentials = "include";
    else if (link.crossOrigin === "anonymous")
      fetchOpts.credentials = "omit";
    else
      fetchOpts.credentials = "same-origin";
    return fetchOpts;
  }
  function processPreload(link) {
    if (link.ep)
      return;
    link.ep = true;
    const fetchOpts = getFetchOpts(link);
    fetch(link.href, fetchOpts);
  }
})();
document.addEventListener("DOMContentLoaded", function() {
  const scriptElement = document.getElementById("script-id");
  var deactivateButton = document.getElementById("deactivate");
  if (scriptElement) {
    scriptElement.addEventListener("input", function() {
      const errorElement = document.getElementById("script-id-error");
      if (this.value.trim() === "") {
        errorElement.style.display = "block";
      } else {
        errorElement.style.display = "none";
      }
    });
  }
  if (deactivateButton) {
    deactivateButton.addEventListener("click", function(event) {
      var userConfirmed = confirm("Are you sure you want to deactivate?");
      if (!userConfirmed) {
        event.preventDefault();
      }
    });
  }
});
