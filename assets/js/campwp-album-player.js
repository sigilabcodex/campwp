(function () {
  function formatTime(seconds) {
    if (!Number.isFinite(seconds) || seconds < 0) {
      return "0:00";
    }

    var rounded = Math.floor(seconds);
    var minutes = Math.floor(rounded / 60);
    var secs = rounded % 60;
    return minutes + ":" + String(secs).padStart(2, "0");
  }

  function initAlbumPlayer(container) {
    var player = container.querySelector("[data-campwp-album-player]");
    if (!player) return;

    var audio = player.querySelector("[data-campwp-audio]");
    var trackName = player.querySelector("[data-campwp-current-track]");
    var currentTimeEl = player.querySelector("[data-campwp-current-time]");
    var durationEl = player.querySelector("[data-campwp-duration]");
    var seek = player.querySelector("[data-campwp-seek]");
    var toggleBtn = player.querySelector('[data-campwp-action="toggle"]');
    var prevBtn = player.querySelector('[data-campwp-action="prev"]');
    var nextBtn = player.querySelector('[data-campwp-action="next"]');
    var toggleIcon = toggleBtn
      ? toggleBtn.querySelector("[data-campwp-icon]")
      : null;

    var icons = {
      play:
        '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><polygon points="6,4 20,12 6,20" fill="currentColor"></polygon></svg>',
      pause:
        '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><rect x="6" y="4" width="4" height="16" fill="currentColor"></rect><rect x="14" y="4" width="4" height="16" fill="currentColor"></rect></svg>',
    };

    var tracks = Array.prototype.slice.call(
      container.querySelectorAll(".campwp-track-row")
    );

    if (!audio || tracks.length === 0) return;

    function setToggleState(isPlaying) {
      if (!toggleBtn) return;
      toggleBtn.setAttribute("aria-label", isPlaying ? "Pause" : "Play");
      toggleBtn.dataset.campwpToggleState = isPlaying ? "playing" : "paused";
      if (toggleIcon) {
        toggleIcon.innerHTML = isPlaying ? icons.pause : icons.play;
      }
    }

    var activeIndex = tracks.findIndex(function (row) {
      return row.dataset.campwpAudioSrc;
    });
    if (activeIndex < 0) activeIndex = 0;

    function setActiveUI() {
      tracks.forEach(function (row, idx) {
        row.classList.toggle("is-active", idx === activeIndex);
      });
    }

    function setTrack(index, keepPlaying) {
      var row = tracks[index];
      if (!row) return;

      var src = row.dataset.campwpAudioSrc || "";
      var type = row.dataset.campwpAudioType || "";
      var title = row.dataset.campwpTitle || "No track selected";

      activeIndex = index;
      setActiveUI();
      trackName.textContent = title;
      durationEl.textContent = row.dataset.campwpDuration || "0:00";
      seek.value = "0";
      currentTimeEl.textContent = "0:00";

      if (!src) {
        audio.removeAttribute("src");
        audio.load();
        setToggleState(false);
        return;
      }

      audio.src = src;
      if (type) {
        audio.setAttribute("type", type);
      } else {
        audio.removeAttribute("type");
      }
      audio.load();

      if (keepPlaying) {
        audio.play();
      }
    }

    tracks.forEach(function (row, index) {
      var button = row.querySelector('[data-campwp-action="track-select"]');
      if (!button) return;

      button.addEventListener("click", function () {
        var keepPlaying = !audio.paused && !audio.ended;
        setTrack(index, keepPlaying);
      });
    });

    if (toggleBtn) {
      toggleBtn.addEventListener("click", function () {
        if (!audio.src) {
          setTrack(activeIndex, true);
          return;
        }

        if (audio.paused) {
          audio.play();
        } else {
          audio.pause();
        }
      });
    }

    if (prevBtn) {
      prevBtn.addEventListener("click", function () {
        var nextIndex = activeIndex > 0 ? activeIndex - 1 : tracks.length - 1;
        setTrack(nextIndex, !audio.paused && !audio.ended);
      });
    }

    if (nextBtn) {
      nextBtn.addEventListener("click", function () {
        var nextIndex = (activeIndex + 1) % tracks.length;
        setTrack(nextIndex, !audio.paused && !audio.ended);
      });
    }

    audio.addEventListener("play", function () {
      setToggleState(true);
    });

    audio.addEventListener("pause", function () {
      setToggleState(false);
    });

    audio.addEventListener("timeupdate", function () {
      currentTimeEl.textContent = formatTime(audio.currentTime);
      if (Number.isFinite(audio.duration) && audio.duration > 0) {
        seek.value = String((audio.currentTime / audio.duration) * 100);
      }
    });

    audio.addEventListener("loadedmetadata", function () {
      if (Number.isFinite(audio.duration) && audio.duration > 0) {
        durationEl.textContent = formatTime(audio.duration);
      }
    });

    audio.addEventListener("ended", function () {
      var nextIndex = (activeIndex + 1) % tracks.length;
      setTrack(nextIndex, false);
    });

    if (seek) {
      seek.addEventListener("input", function () {
        if (!Number.isFinite(audio.duration) || audio.duration <= 0) return;
        var percent = Number(seek.value) / 100;
        audio.currentTime = percent * audio.duration;
      });
    }

    setTrack(activeIndex, false);
    setToggleState(false);
  }

  function initCoverLightbox(container) {
    var openButton = container.querySelector("[data-campwp-cover-open]");
    var lightbox = container.querySelector("[data-campwp-cover-lightbox]");
    if (!openButton || !lightbox) return;

    var closeButtons = lightbox.querySelectorAll("[data-campwp-cover-close]");

    function closeLightbox() {
      lightbox.hidden = true;
      document.body.classList.remove("campwp-lightbox-open");
    }

    function openLightbox() {
      lightbox.hidden = false;
      document.body.classList.add("campwp-lightbox-open");
    }

    openButton.addEventListener("click", openLightbox);
    closeButtons.forEach(function (button) {
      button.addEventListener("click", closeLightbox);
    });

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && !lightbox.hidden) {
        closeLightbox();
      }
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    var albumContainers = document.querySelectorAll(".campwp-album");
    albumContainers.forEach(function (container) {
      initAlbumPlayer(container);
      initCoverLightbox(container);
    });
  });
})();
