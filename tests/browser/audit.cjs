const {chromium}=require('playwright-core');
const AxeBuilder=require('@axe-core/playwright').default;
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/usr/bin/chromium',args:['--no-sandbox']});
 const context=await browser.newContext({viewport:{width:1440,height:1000}});const page=await context.newPage();
 const base=process.env.TEST_BASE_URL||'http://127.0.0.1:8000';let failures=0;
 async function audit(route){const response=await page.goto(base+route);if(response.status()!==200)throw Error(`HTTP ${response.status()}: ${route}`);const result=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();failures+=result.violations.length;console.log(JSON.stringify({route,violations:result.violations.map(v=>({id:v.id,impact:v.impact,nodes:v.nodes.map(n=>({target:n.target,summary:n.failureSummary}))}))}));}
 for(const route of ['/','/research','/research/match','/mentors','/events','/events?view=calendar','/knowledge','/login','/register','/about'])await audit(route);
 await page.goto(base+'/login');await page.locator('#email').fill('organizer@example.test');await page.locator('#password').fill(process.env.TEST_DEMO_PASSWORD||'demo-local-only-2026');await Promise.all([page.waitForURL('**/dashboard'),page.getByRole('button',{name:'Войти в кабинет'}).click()]);
 for(const route of ['/dashboard','/dashboard?tab=profile','/dashboard?tab=applications','/dashboard?tab=help','/manage/tasks','/manage/tasks/create','/manage/events/create','/manage/contents/create','/manage/mentors/create','/manage/statistics','/manage/settings'])await audit(route);
 await browser.close();if(failures)process.exitCode=1;
})().catch(error=>{console.error(error);process.exit(1);});
