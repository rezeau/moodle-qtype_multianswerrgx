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
 * @copyright  2023 Jun Pataleta <jun@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* The data is stored in a hidden field */
define(['jquery'], function($) {
    /* eslint-disable no-console */
  return {
    init: function() {

      // Init the css for the error divs.
      let indexes = [5, 9];
      for (let i = 0; i < indexes.length; i++) {
        $('#id_error_button_group_add_gaps_' + indexes[i]).css({
          display: 'inline',
          color: 'red',
        });
      }

      /* A click on the Add gaps 1/5 button. */
      $('#id_button_group_add_gaps_5').on('click', function() {
        createGaps (5);
      });

      /* A click on the Add gaps 1/9 button. */
      $('#id_button_group_add_gaps_9').on('click', function() {
        createGaps (9);
      });

      /* A click on the Remove gaps button. */
      $('#id_button_group_remove_gaps_button').on('click', function() {
        var iframe = $('#id_questiontext_ifr');
        var iframeBody = iframe.contents().find('body');
        var textContent = iframeBody.text();
        var paragraphs = iframeBody.find('p');
        // Regular expression to detect the presence of sub-questions in question text.
        var regex = /\{[^}]*[^}]*\}/g;
        var containsGaps = regex.test(textContent);
        if (containsGaps) {
          for (let i = 0; i < paragraphs.length; i++) {
            let paraText = $(paragraphs[i]).text();
            const cleanedText = paraText.replace(/\{[^:]+:[^:]+:=(.*?)(#.*?)?\}/g, '$1');
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
      function createGaps (interval) {
        // Init error divs.
        $('#id_error_button_group_add_gaps_5').html('');
        $('#id_error_button_group_add_gaps_9').html('');
        var iframe = $('#id_questiontext_ifr');
        var iframeBody = iframe.contents().find('body');
        var textContent = iframeBody.text();
        var paragraphs = iframeBody.find('p');
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
        let totalWords = textContent.split(' ');
        if (totalWords.length < interval) {
          $('#id_error_button_group_add_gaps_' + interval).html(M.util.get_string(
            'tooshortforgapserror',
            'qtype_multianswerrgx'
          ));
          return '';
        }
        let words;
        let gappedText;
        let paraText;
        for (let i = 0; i < paragraphs.length; i++) {
          paraText = $(paragraphs[i]).text();
          words = paraText.split(' ');
          // Loop through the words and enclose every 5th or 9th word in SHORTANSWER marker.
          for (let index = 0; index < words.length; index++) {
            if ((index + 1) % interval === 0) {
              // Separate the word from any trailing punctuation
              let word = words[index];
              let punctuation = '';
              // Check if the word ends with punctuation
              if (/[.,!?;:]+$/.test(word)) {
                  punctuation = word.slice(-1); // Get the punctuation mark
                  word = word.slice(0, -1); // Remove the punctuation from the word
              }
              // Check if the word starts with capital letter and skip it. TODO.
              // Enclose the word in square brackets, then add back the punctuation
              words[index] = `{1:SA:=${word}}${punctuation}`;
            }
          }
          // Join the words back into a single string
          gappedText = words.join(' ');
          //$('#id_add_gaps_5, #id_add_gaps_7').prop('disabled', true);
          if (gappedText !== '') {
            $(paragraphs[i]).text(gappedText);
          }
          $('#id_button_group_remove_gaps_button').prop('disabled', false);
        }
      }
    }
  };
});
