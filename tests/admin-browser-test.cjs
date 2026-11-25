'use strict';
const assert=require('node:assert/strict');
const {chromium}=require('playwright');
(async()=>{
 const browser=await chromium.launch({headless:true});
 const base=process.env.CD_TEST_BASE,token=process.env.CD_TEST_TOKEN;
 const errors=[];
 try{
  const page=await browser.newPage({viewport:{width:1920,height:1080}});
  page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'?player=1#'+token);
  await page.locator('main h1').filter({hasText:'NTC 93'}).waitFor();
  assert.match(await page.locator('#status').innerText(),/Verbunden/);
  await page.locator('#next').click();
  await page.locator('main h1').filter({hasText:'HTTP-Test'}).waitFor();
  await page.locator('main img:not([hidden])').waitFor({state:'visible'});
  assert.match(await page.locator('main img').getAttribute('src'),/^blob:/);
  const imageBox=await page.locator('main img').boundingBox();assert.ok(imageBox&&imageBox.width>0&&imageBox.height>0);
  assert.equal(await page.locator('main script').count(),0);
  assert.match(await page.locator('main p').innerText(),/<script>example<\/script>/);
  const mainBox=await page.locator('main').boundingBox();const contentBox=await page.locator('.entry-layout').boundingBox();assert.ok(contentBox.y+contentBox.height<=mainBox.y+mainBox.height+1);
  await page.locator('#pause').click();
  assert.equal(await page.locator('#pause').getAttribute('aria-label'),'Play');
  await page.screenshot({path:'artifacts/managed-screen-desktop.png'});
  await page.setViewportSize({width:390,height:844});
  for(const selector of ['header','main','footer','.entry-layout']){const b=await page.locator(selector).boundingBox();assert.ok(b&&b.x>=-1&&b.x+b.width<=391,selector+' exceeds mobile viewport');}
  await page.screenshot({path:'artifacts/managed-screen-mobile.png'});
  const club=await browser.newPage({viewport:{width:1280,height:900}});
  club.on('pageerror',e=>errors.push(e.message));
  await club.goto(base+'?portal=club&verein=ntc93');
  await club.locator('input[name=password]').fill('test-club-password-123456');
  await club.getByRole('button',{name:'Anmelden',exact:true}).click();
  await club.getByRole('heading',{name:'Vereinsbackend'}).waitFor();
  await club.goto(base+'?portal=club&verein=ntc93&preview=1&club=ntc93&screen=hall');
  await club.locator('main h1').filter({hasText:'NTC 93'}).waitFor();
  assert.match(await club.locator('#status').innerText(),/VORSCHAU/);
  await club.locator('#next').click();
  await club.locator('main h1').filter({hasText:'HTTP-Test'}).waitFor();
  await club.locator('main img:not([hidden])').waitFor({state:'visible'});
  assert.match(await club.locator('main img').getAttribute('src'),/^blob:/);
  await club.screenshot({path:'artifacts/managed-preview.png'});
  assert.deepEqual(errors,[]);
  console.log('Chromium: actual player, images, mobile, preview, safe text rendering passed.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
