// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Backward compatibility file for the old popover.js
 *
 * @module     qtype_multianswerrgx/feedback
 * @copyright  2024 Joseph Rézeau <moodle@rezeau.org>
 * @copyright  based on original work by 2023 Jun Pataleta <jun@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* The data is stored in a hidden field */
define(['jquery'], function($) {
  return {
    init: function() {
      // Init the css for the error divs.
      let paragraphs;
      let textContent;
      let indexes = [5, 9];
      for (let i = 0; i < indexes.length; i++) {
        $('#id_error_button_group_add_gaps_' + indexes[i]).css({
          display: 'inline',
          color: 'red',
        });
      }
      // Check the state of the Checkbox to enable skipping capitalised words or not.
      var skipcapswordscheck = $('#id_button_group_skip_caps_words');

      /* A click on the Add gaps 1/5 button. */
      $('#id_button_group_add_gaps_5').on('click', function() {
        createGaps(5);
      });

      /* A click on the Add gaps 1/9 button. */
      $('#id_button_group_add_gaps_9').on('click', function() {
        createGaps(9);
      });

      /* A click on the Remove gaps button. */
      $('#id_button_group_remove_gaps_button').on('click', function() {
        var iframe = $('#id_questiontext_ifr');
        var iframeBody = iframe.contents().find('body');
        textContent = iframeBody.text();
        paragraphs = iframeBody.find('p').filter(function() {
          // Exclude paragraphs that contain <img>, <audio>, or <video> tags
          return $(this).find('img, audio, video').length === 0;
        });
        // Regular expression to detect the presence of sub-questions in question text.
        var regex = /\{[^}]*[^}]*\}/g;
        var containsGaps = regex.test(textContent);
        let paraText;
        let cleanedText;
        if (containsGaps) {
          for (let i = 0; i < paragraphs.length; i++) {
            paraText = $(paragraphs[i]).text();
            cleanedText = paraText.replace(/{[^=]*(=|%100%)([^#}]*)[^}]*}/g, '$2');
            $(paragraphs[i]).text(cleanedText);
          }
        }
        $('#id_button_group_remove_gaps_button').prop('disabled', true);
        $('#id_error_button_group_add_gaps_5').html('');
        $('#id_error_button_group_add_gaps_9').html('');
        return;
      });

      /**
       * Encloses every nth word in square brackets, keeping punctuation outside the brackets.
       * @param {number} interval - The interval at which to enclose words in brackets.
       * @returns {string} The modified text with every nth word enclosed in brackets.       *
       */
      function createGaps(interval) {
        // Init error divs.
        $('#id_error_button_group_add_gaps_5').html('');
        $('#id_error_button_group_add_gaps_9').html('');
        var skipcapswords = skipcapswordscheck.prop('checked');
        const capsWords = new Array();
        let enoughWords;
        var iframe = $('#id_questiontext_ifr');
        var iframeBody = iframe.contents().find('body');
        textContent = iframeBody.text();
        paragraphs = iframeBody.find('p').filter(function() {
          // Exclude paragraphs that contain <img>, <audio>, or <video> tags
          return $(this).find('img, audio, video').length === 0;
        });
        // Regular expression to detect the presence of sub-questions in question text.
        var pattern = /\{[^}]*[^}]*\}/g;
        // Check if the pattern matches the string
        if (pattern.test(textContent)) {
          $('#id_error_button_group_add_gaps_' + interval).html(M.util.get_string(
            'addgapserror',
            'qtype_multianswerrgx'
          ));
          return;
        }
        // Check if there are enough words at least in one "gappable" paragraph.
        let totalWords = 0;
        let paratext;
        for (let i = 0; i < paragraphs.length; i++) {
          paratext = $(paragraphs[i]).text();
          totalWords = paratext.split(' ');
          enoughWords = false;
          if (totalWords.length > interval) {
            enoughWords = true;
            continue;
          }
        }
        if (!enoughWords) {
          $('#id_error_button_group_add_gaps_' + interval).html(M.util.get_string(
            'tooshortforgapserror',
            'qtype_multianswerrgx'
          ));
          return;
        }
        for (let i = 0; i < paragraphs.length; i++) {
          let paraText = $(paragraphs[i]).text();
          paraText = paraText.replace(/\s+/g, ' ').trim();
          let words = paraText.split(' ');
          // With many thanks to Mark Johnson for this script.
          // Loop through the words and enclose every 5th or 9th word in SHORTANSWER marker.
           let offset = 1;
           for (let index = 0; index < words.length; index++) {
             if ((index + offset) % interval === 0) {
               // Separate the word from any trailing punctuation
               let word = words[index];
               let punctuation = '';
              if (/[.,!?;:]+$/.test(word)) {
                  punctuation = word.slice(-1); // Get the punctuation mark
                  word = word.slice(0, -1); // Remove the punctuation from the word
              }
              // Check if the word starts with a capital letter
              if (skipcapswords && word && word[0] === word[0].toUpperCase() && /[A-Za-z]/.test(word[0])
                  && !capsWords.includes(word)) {
                // If the word starts with a capital letter, skip the gapping transformation
                // Do not skip the gapping transformation if capitalised word has already been gapped.
                offset -= 1;
                // Add new capitalised word to the capsWords list.
                capsWords.push(word);
                continue;
              }
              // Enclose the word in SHORTANSWER (SA) brackets, then add back the punctuation
              words[index] = `{1:SA:=${word}}${punctuation}`;
            }
          }
          // Join the words back into a single string
          let gappedText = words.join(' ');
          if (gappedText !== '') {
            $(paragraphs[i]).text(gappedText);
          }
          $('#id_button_group_remove_gaps_button').prop('disabled', false);
        }
      }
    }
  };
});
