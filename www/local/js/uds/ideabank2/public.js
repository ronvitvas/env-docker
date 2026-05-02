(function(){
  function cleanupPageChrome(){
    document.querySelectorAll('.breadcrumbs').forEach(function(node){
      node.remove();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', cleanupPageChrome);
  } else {
    cleanupPageChrome();
  }

  setTimeout(cleanupPageChrome, 500);

  document.addEventListener('click', function(event){
    var support = event.target.closest('[data-uds-support]');
    if (support) {
      event.preventDefault();
      alert('Опишите проблему администратору банка идей. Полноценная форма поддержки будет подключена отдельным компонентом.');
    }

    var shareButton = event.target.closest('[data-uds-share-idea]');
    if (shareButton) {
      event.preventDefault();

      var panel = shareButton.closest('.idea-share-panel');
      var select = panel ? panel.querySelector('[data-uds-share-idea-select]') : null;
      if (!select || !select.value) {
        return;
      }

      var selected = select.options[select.selectedIndex];
      var title = selected ? (selected.getAttribute('data-title') || selected.textContent || 'Идея') : 'Идея';
      var url = new URL(select.value, window.location.origin).toString();
      var text = 'Поддержите мою идею в банке идей: ' + title;

      if (navigator.share) {
        navigator.share({title: title, text: text, url: url}).catch(function(){});
        return;
      }

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function(){
          alert('Ссылка на идею скопирована. Отправьте её коллегам в чат или почту.');
        }).catch(function(){
          window.location.href = 'mailto:?subject=' + encodeURIComponent(title) + '&body=' + encodeURIComponent(text + '\n' + url);
        });
        return;
      }

      window.location.href = 'mailto:?subject=' + encodeURIComponent(title) + '&body=' + encodeURIComponent(text + '\n' + url);
    }

    var ideaTab = event.target.closest('[data-uds-idea-tab]');
    if (ideaTab) {
      event.preventDefault();

      var widget = ideaTab.closest('[data-uds-idea-tabs]');
      if (!widget) {
        return;
      }

      var code = ideaTab.getAttribute('data-uds-idea-tab');
      widget.querySelectorAll('[data-uds-idea-tab]').forEach(function(tab){
        var active = tab === ideaTab;
        tab.classList.toggle('is-active', active);
        tab.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      widget.querySelectorAll('[data-uds-idea-panel]').forEach(function(panel){
        var active = panel.getAttribute('data-uds-idea-panel') === code;
        panel.classList.toggle('is-active', active);
        panel.hidden = !active;
      });
    }
  });
})();
