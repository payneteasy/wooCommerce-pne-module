<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

get_header();
?>

  <style>
    .payneteasy-container {
      margin: auto;
      width: 100%;
      max-width: 500px;
      padding: 20px;
      text-align: center;
    }

    .payneteasy-progress {
      height: 40px;
      line-height: 40px;
      position: relative;
      margin: 20px 0 20px 0;
      background: rgba(0,0,0,0.5);
      border-radius: 8px;
      box-shadow        : inset 0 -1px 1px rgba(255,255,255,0.5);
    }
    .payneteasy-progress > span {
      display: block;
      height: 40px;
      border-top-right-radius: 8px;
      border-bottom-right-radius: 8px;
      border-top-left-radius: 8px;
      border-bottom-left-radius: 8px;
      background-color: rgb(43,194,83);
      background-image: linear-gradient(
              center bottom,
              rgb(43,194,83) 37%,
              rgb(84,240,84) 69%
      );
      box-shadow:
              inset 0 2px 9px  rgba(255,255,255,0.3),
              inset 0 -2px 6px rgba(0,0,0,0.4);
      position: relative;
      overflow: hidden;
      transition: all 2s;
    }
    .payneteasy-progress > span:after, .animate > span > span {
      content: "";
      position: absolute;
      top: 0; left: 0; bottom: 0; right: 0;
      background-image:
              linear-gradient(
                      -45deg,
                      rgba(255, 255, 255, .2) 25%,
                      transparent 25%,
                      transparent 50%,
                      rgba(255, 255, 255, .2) 50%,
                      rgba(255, 255, 255, .2) 75%,
                      transparent 75%,
                      transparent
              );
      z-index: 1;
      background-size: 50px 50px;
      animation: move 2s linear infinite;
      border-top-left-radius: 20px;
      border-bottom-left-radius: 20px;
      overflow: hidden;
    }

    .animate > span:after {
      display: none;
    }

    .payneteasy-progress .progress-label {
      color: aliceblue;
      font-weight: bold;
      text-align: center;
      text-shadow: 1px 1px 1px #4f4f4f;
    }

    @keyframes move {
      0% {
        background-position: 0 0;
      }
      100% {
        background-position: 50px 50px;
      }
    }
  </style>
  <script>

      jQuery(document).ready(function () {

          var $progress               = jQuery('#payneteasy-progress');

          var current                 = 0;
          var wait                    = 30;
          var timerId                 = setInterval(function() {

              current++;

              var progress            = Math.floor(current / wait * 100);
              $progress.css('width', progress + '%');

              if(current >= wait) {
                  clearInterval(timerId);
                  location.reload();
              }

          }, 1000);
      });
  </script>


  <div class="payneteasy-container">

    <h1><?=__('Progress Title', 'paynet-easy-gateway')?></h1>
    <p><?=__('Progress message', 'paynet-easy-gateway')?></p>

    <form action="#" method="post">
      <div class="payneteasy-progress animate">
        <span id="payneteasy-progress" style="width: 1%"><span class="progress-label">&nbsp;</span></span>
      </div>
      <input type="submit" value="<?=__('Update', 'paynet-easy-gateway')?>" />
    </form>

  </div>

<?php
get_footer();
