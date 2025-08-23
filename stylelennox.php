<style>
/* Décalage sous la barre réutilisable si besoin */
.tabs { margin-top: 12px; }

.cadreCTA {
  position: relative;
  width: 800px;
  height: 475px;
  margin-left: auto !important;
  margin-right: auto !important;
  display: flex;
  justify-content: center;
  align-items: center;
  background: #fff;
  border: 2px solid #bbb;
  border-top: none;
  border-radius: 0 12px 12px 12px;
  box-shadow: 0 2px 8px #0002;
  overflow: hidden;
  box-sizing: border-box;
}

.config-button { background: transparent; border: none; padding: 4px; color: #193cf1; }
.config-button svg { width:28px; height:28px; display:block; fill: currentColor; }

.CtaImageFond 
{
        position: absolute;
        top: 0;
        left: 0;
        z-index: 1;
}

.etiquette
{
    position: absolute;
    font-size: 1.11em;
    color: #193cf1;
    font-weight: bold;
    text-shadow: 1px 2px 4px #ffffff8c;
    padding: 4px 12px;
    border: 1px solid gray;
    z-index: 10;
    background: #dfdfdf;
    border-radius: 12px;
    box-shadow: inset 3px 2px 6px #878686d9, inset -2px -3px 3px #cdcccce6, 4px 3px 4px #45424270;
    width: 3em;
    text-align: center;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
    -webkit-touch-callout: none;
}

.eti1 { top: 20px; left: 135px; }
.eti2 { top: 106px; left: 378px; }
.eti3 { top: 161px; left: 708px; }
.eti4 { top: 20px; left: 707px; }
.eti5 { top: 161px; left: 3px; }
.eti6 { top: 307px; left: 164px; }
.eti7 { top: 307px; left: 504px; }
.eti8 { top: 307px; left: 378px; }
.eti9 { top: 348px; left: 378px; }
.eti10 { top: 389px; left: 378px; }
.eti11 { top: 20px; left: 378px; }
.eti12 { top: 63px; left: 378px; }

.labeltt {
    position: absolute;
    font-size: 1.11em;
    color: #082bb3;
    z-index: 10;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
}
.tt1 { top: 353px; left: 263px; }
.tt2 { top: 394px; left: 263px; }
.tt3 { top: 14px; left: 275px; text-align: right; }
.tt4 { top: 56px; left: 295px; text-align: right; }
.tt5 { top: 25px; left: 607px; text-align: right; color: #ff0000; font-family: fangsong; font-weight: 900; }
.tt6 { top: 102px; left: 295px; text-align: right; }
.tt7 { top: 445px; left: 20px; font-family: monospace; text-align: right; color: #000000; }

.tabs {
  margin-bottom: 0;
  justify-content: flex-start;
  width: 800px;
  margin-left: auto !important;
  margin-right: auto !important;
  border-bottom: none;
  border-radius: 12px 12px 0 0;
  overflow: hidden;
  position: relative;
  box-sizing: border-box;
}

.tabsRow {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 800px;
  margin-left: auto !important;
  margin-right: auto !important;
  box-sizing: border-box;
}

.config-button {
  padding: 8px 12px;
  font-size: 1.05em;
  border-radius: 8px;
  background: #e0e0e0;
  color: #193cf1;
  border: 1px solid #bbb;
  cursor: pointer;
  box-shadow: 0 2px 8px #0002;
  margin-left: 12px;
}

.config-button:hover { box-shadow: 0 6px 20px #0003; }

.tab-button {
  font-family: serif;
  padding: 10px 30px;
  font-size: 1.1em;
  cursor: pointer;
  background: #e0e0e0;
  border: 1px solid #bbb;
  border-bottom: none;
  border-radius: 8px 8px 0 0;
  margin-right: 0;
  font-weight: bold;
  transition: box-shadow 0.45s;
  position: relative;
  z-index: 1;
}
.tab-button:not(.active) {
  transform: none;
}
.tab-button.active {
  background: #fff;
  color: #193cf1;
  box-shadow: 0 6px 24px #193cf180;
  z-index: 3;
  transition: box-shadow 0.45s;
  transform-origin: left center;
}
.tab-content {
  display: none;
}
.tab-content.active {
      display: block;
      transform: scale(1);
      box-shadow: none;
      z-index: 2;
    }

.tab-icon-red {
  height: 1.6em;
  vertical-align: middle;
  margin-right: 4px;
  margin-left:-18px;
  margin-top:-6px;
  filter: brightness(0) saturate(100%) invert(18%) sepia(99%) saturate(7492%) hue-rotate(-1deg) brightness(97%) contrast(119%);
}

#overlay-config {
  display: none;
  position: fixed;
  inset: 0;
  background: transparent !important;
  z-index: 2000;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(12px);
}
#overlay-config > div {
  width: 100%;
  height: 100%;
  background: transparent !important;
  display: flex;
  align-items: center;
  justify-content: center;
}
#iframe-config-lennox {
  width: 100%;
  height: 100%;
  border-radius: 0;
  border: 0;
  box-shadow: none;
  display: block;
}
</style>
