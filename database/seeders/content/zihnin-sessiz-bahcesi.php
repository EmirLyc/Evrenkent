<?php

/*
 * Demo içerik — "Zihnin Sessiz Bahçesi" (Deniz Yalçın, psikoloji / deneme). LibraryDemoSeeder okur.
 * Okuma modunun bütün özelliklerini göstersin diye: önsöz (giriş bölümü, roma rakamlı sayfalar),
 * İçindekiler, ara başlıklar, dipnot (harf), kaynak (rakam) ve kaynakça.
 * Blok sözdizimi: "## " / "### " ara başlık, "> " alıntı, "---" süs ayırıcı, "@icindekiler",
 * "@kaynakca", [[dn:…]] dipnot, [[kaynak:…]] kaynak numarası.
 */

$james = 'William James, *The Principles of Psychology*, New York: Henry Holt, 1890.';
$simon = 'Herbert A. Simon, "Designing Organizations for an Information-Rich World", *Computers, Communications, and the Public Interest* içinde, Baltimore: Johns Hopkins Press, 1971.';
$csikszentmihalyi = 'Mihaly Csikszentmihalyi, *Flow: The Psychology of Optimal Experience*, New York: Harper & Row, 1990.';
$honore = 'Carl Honoré, *In Praise of Slow*, Londra: Orion, 2004.';
$kahneman = 'Daniel Kahneman, *Thinking, Fast and Slow*, New York: Farrar, Straus and Giroux, 2011.';
$ebbinghaus = 'Hermann Ebbinghaus, *Über das Gedächtnis*, Leipzig: Duncker & Humblot, 1885.';
$bartlett = 'Frederic C. Bartlett, *Remembering: A Study in Experimental and Social Psychology*, Cambridge: Cambridge University Press, 1932.';
$kierkegaard = 'Søren Kierkegaard, *Kaygı Kavramı (Begrebet Angest)*, 1844.';
$may = 'Rollo May, *The Meaning of Anxiety*, New York: Ronald Press, 1950.';
$duhigg = 'Charles Duhigg, *The Power of Habit*, New York: Random House, 2012.';
$winnicott = 'Donald W. Winnicott, "The Capacity to be Alone", *International Journal of Psycho-Analysis*, 39, 1958.';
$storr = 'Anthony Storr, *Solitude: A Return to the Self*, New York: Free Press, 1988.';
$pascal = 'Blaise Pascal, *Düşünceler (Pensées)*, 1670.';

return [
    'preface' => [
        '@icindekiler',
        '## Önsöz',
        'Bu kitap bir bahçe hakkında. Ama toprağı, çiçekleri ya da ağaçları olan bir bahçe değil; her birimizin içinde taşıdığı, çoğu zaman bakımsız bıraktığımız, bazen de fark etmeden betona gömdüğümüz o iç mekân hakkında. Zihnin bahçesi.',
        'Psikoloji üzerine yazılmış kitapların çoğu ya bir sorunu çözmeyi vaat eder ya da bir teşhis koyar. Bu kitap ikisini de yapmıyor. Bunun yerine, zihnimizin bazı bildik hallerine, dikkat, yavaşlık, hafıza, kaygı, alışkanlık ve yalnızlık, bir bahçıvanın gözüyle bakmayı deniyor. Bahçıvan bir bitkiyi zorla büyütmez; toprağını hazırlar, suyunu verir, yabani otları temizler ve bekler.',
        'Her bölüm kendi başına okunabilir. Ama sırayla okuyan okur, bölümlerin birbirine nasıl bağlandığını da görecektir: dikkat olmadan yavaşlık, yavaşlık olmadan hatırlama, hatırlama olmadan da kendimizle baş başa kalma mümkün değildir.',
        'Metinde yer yer küçük harflerle dipnotlar, rakamlarla da kaynak numaraları göreceksiniz. Dipnotlar bir kavramı açıklıyor ya da bir yan yola sapıyor; kaynak numaraları ise düşüncesinden yararlandığım eserlere işaret ediyor. Kaynakçanın tamamı kitabın sonunda.',
        'İyi okumalar. Ve acele etmeyin.',
    ],
    [
        'title' => 'Dikkatin Ekonomisi',
        'blocks' => [
            'Sabah uyandığınızda gözünüzü ilk açtığınız şey nedir? Çoğumuz için cevap bellidir: telefonun ekranı. Daha ayaklarımız yere değmeden bildirimler, mesajlar, haberler ve başkalarının hayatlarından kesitler zihnimize akmaya başlar. Günün ilk dakikalarında, henüz kendimize ait tek bir düşünce üretmeden, dikkatimiz çoktan başkalarına dağıtılmıştır.',
            'Psikolojinin kurucu figürlerinden William James, yüz otuz yılı aşkın bir süre önce şunu yazmıştı: Deneyimim, dikkat etmeyi kabul ettiğim şeydir.[[kaynak:'.$james.']] Bu cümle ilk bakışta sade görünür. Ama altında sarsıcı bir iddia yatar: Neye dikkat ettiğimiz, kim olduğumuzu belirler. Dikkat etmediğimiz şey, bizim için hiç yaşanmamış gibidir.',
            '## Bolluğun Yoksulluğu',
            'Ekonomist ve bilişsel bilimci Herbert Simon, 1971\'de bilgi çağının temel paradoksunu formüle etti: Bilginin bolluğu, dikkatin yoksulluğunu yaratır.[[kaynak:'.$simon.']] Bilgi tüketen bir şey vardır ve bu şey, onu alanların dikkatidir. Simon bunu henüz kişisel bilgisayarların, internetin ve akıllı telefonların olmadığı bir dönemde söylüyordu.',
            'Bugün bu yoksulluğu her gün yaşıyoruz. Bir kitabın on sayfasını kesintisiz okumakta zorlanıyor, bir filmi izlerken elimiz telefona gidiyor, bir sohbetin ortasında aklımız başka bir yere kayıyor. Bu bir karakter zaafı değil; dikkatimizi kazanmak için tasarlanmış bir çevrenin doğal sonucu.',
            'Dikkatin bir ekonomisi vardır, çünkü dikkat sınırlıdır. Aynı anda iki karmaşık metni okuyamayız, iki konuşmayı birden gerçekten dinleyemeyiz. Çoklu görev dediğimiz şey, çoğu zaman görevler arasında hızla gidip gelmektir ve her geçişin bir bedeli vardır.[[dn:Bilişsel psikolojide buna "görev değiştirme maliyeti" denir: bir işten ötekine geçen zihin, yeni işin kurallarını yeniden yüklemek için kısa ama ölçülebilir bir süre kaybeder.]]',
            '## Akış Hali',
            'Peki dikkatimiz tümüyle bir şeye yöneldiğinde ne olur? Macar asıllı psikolog Mihaly Csikszentmihalyi, bu soruya ömrünü adadı. Cerrahlar, dağcılar, satranç oyuncuları, ressamlar ve fabrika işçileriyle yaptığı görüşmelerde hep aynı deneyimi tarif eden insanlarla karşılaştı: zamanın unutulduğu, benliğin sessizleştiği, yapılan işin kendisinin ödül olduğu anlar. Buna akış adını verdi.[[kaynak:'.$csikszentmihalyi.']]',
            'Akışın ilginç bir özelliği vardır: kolay işlerde ortaya çıkmaz. Çok kolay olan iş can sıkar, çok zor olan kaygı yaratır. Akış, becerimizin sınırında, bizi biraz zorlayan ama altından kalkabileceğimiz işlerde belirir. Bir bahçıvan için bu, toprağı ellemek, budama yapmak, bir fidanı doğru yere dikmektir.',
            '> Akış, dikkatin bir bahçesidir. Oraya zorla girilmez; kapısı ancak dikkat bir süre dağılmadan beklediğinde açılır.',
            '## Küçük Bir Deney',
            'Bu bölümü bitirmeden önce size küçük bir deney öneriyorum. Yarın sabah, uyandıktan sonraki ilk on beş dakika boyunca hiçbir ekrana bakmayın. Pencereden dışarı bakın, bir bardak su için, belki birkaç sayfa bir şey okuyun. Ne düşündüğünüzü fark edin. Aklınıza gelen ilk düşüncenin sizin mi, yoksa bir ekrandan mı geldiğini ayırt etmeye çalışın.',
            'Bu, bahçeye atılan ilk adım. Henüz hiçbir şey dikmiyoruz; yalnızca toprağın nerede olduğunu görüyoruz.',
        ],
    ],
    [
        'title' => 'Yavaşlığın Hakkı',
        'blocks' => [
            'Hızın erdem sayıldığı bir dünyada yaşıyoruz. Hızlı internet, hızlı teslimat, hızlı yemek, hızlı karar. Bir işi çabuk bitiren takdir edilir, yavaş olan ise "verimsiz" diye etiketlenir. Oysa doğada hiçbir şey acele etmez ve yine de her şey vaktinde olur.',
            'Kanadalı gazeteci Carl Honoré, bir gün çocuğuna masal okurken kendini hikâyeyi kısaltmaya çalışırken yakalamış; hatta "bir dakikalık masallar" diye bir kitap almayı ciddi ciddi düşündüğünü fark etmiş. Bu an, onu yavaşlık üzerine bir araştırmaya itti.[[kaynak:'.$honore.']] Vardığı sonuç basitti: Sorun hız değildi; sorun, her şeyi aynı hızda yapma zorunluluğuydu.',
            '## Hızlı ve Yavaş Düşünme',
            'Nobel ödüllü psikolog Daniel Kahneman, zihnimizde iki farklı düşünme biçiminin çalıştığını anlatır.[[kaynak:'.$kahneman.']] Birincisi hızlı, sezgisel ve zahmetsizdir: bir yüzdeki öfkeyi anında fark etmemiz, iki artı ikinin dört olduğunu düşünmeden bilmemiz. İkincisi yavaş, dikkatli ve emek isteyen düşünmedir: karmaşık bir hesap yapmak, bir karşılaştırmayı tartmak, bir mektubu özenle yazmak.',
            'İki sistem de gereklidir. Ama hızlı sistem, yavaş sistemin işini de üstlenmeye meyillidir. Acele ettiğimizde, yorgun olduğumuzda, dikkatimiz dağınıkken, yavaş düşünmemiz gereken yerlerde bile hızlı sisteme güveniriz. Ve hızlı sistem, sezgisel olduğu kadar önyargılıdır da.',
            'Yavaşlamak, bu açıdan bir lüks değil bir zorunluluktur. Önemli kararlar, derin ilişkiler, gerçek öğrenme; bunların hepsi yavaş sistemin zeminde çalışmasını gerektirir.',
            '## Bahçede Zaman',
            'Bir bahçıvanla konuşun; size mevsimlerden söz edecektir. Lalelerin sonbaharda dikilip ilkbaharda açtığını, bir ağacın ilk meyvesini yıllar sonra verdiğini, toprağın dinlendirilmesi gerektiğini. Bahçede hiçbir şey hızlandırılamaz. Bir tohumu sabırsızlıkla kazıp bakmak, onu öldürmenin en kısa yoludur.',
            'Zihnimiz de bir bakıma böyledir. Bir fikrin olgunlaşması, bir kaybın yasının tutulması, yeni bir becerinin yerleşmesi zaman alır. Bu süreçleri hızlandırmaya çalıştıkça onları bozarız.[[dn:"Kuluçka dönemi" kavramı burada yerindedir: yaratıcılık araştırmalarında, bir sorun üzerinde bilinçli olarak çalışmayı bırakıp başka işlerle uğraşılan dönemin, ani bir kavrayışla sonuçlanabildiği sıkça aktarılır.]]',
            '## Yavaş Bir Gün',
            'Haftada bir gün, belki yalnızca birkaç saat, bilinçli olarak yavaşlamayı deneyebilirsiniz. Yürürken acele etmeyin; bir yere yetişmek için değil, yürümek için yürüyün. Yemeği ayakta değil oturarak, konuşarak, tadına vararak yiyin. Bir mektup yazın, elle, bir müsvedde yapıp temize çekerek.',
            'Bunları yaptığınızda ilk başta huzursuzluk hissedebilirsiniz. Boşa vakit harcıyormuş gibi. Bu huzursuzluk, hızın bizde bıraktığı bir alışkanlıktır ve geçer. Yerine, uzun zamandır unuttuğumuz bir şey gelir: şimdiki anın dokusu.',
            '> Yavaşlık, zamanı kaybetmek değil, zamanın içinde gerçekten bulunmaktır.',
        ],
    ],
    [
        'title' => 'Hatırlamak ve Unutmak',
        'blocks' => [
            'Hafızayı genellikle bir depo gibi düşünürüz: yaşadıklarımız oraya kaydedilir, gerektiğinde de oradan çıkarılır. Bu benzetme sezgisel olarak doğru görünür ama psikoloji araştırmaları hafızanın bundan çok farklı işlediğini gösteriyor. Hafıza bir arşiv değil, daha çok bir bahçedir: sürekli büyüyen, budanan, yeniden şekillenen canlı bir yapı.',
            '## Unutmanın Eğrisi',
            'Alman psikolog Hermann Ebbinghaus, 1880\'lerde kendisini denek olarak kullandığı titiz deneyler yaptı. Anlamsız hece dizileri ezberliyor, sonra bu dizilerin ne kadarını, ne kadar sürede unuttuğunu ölçüyordu.[[kaynak:'.$ebbinghaus.']] Vardığı sonuç bugün "unutma eğrisi" olarak bilinir: öğrendiğimiz şeylerin büyük bölümünü ilk birkaç saat ve gün içinde hızla unuturuz; sonra unutma yavaşlar.',
            'Ebbinghaus bir şey daha fark etti: aynı bilgiyi aralıklarla tekrar ettiğimizde, unutma eğrisi her seferinde daha yavaş iner. Bir şeyi bir kerede on kez tekrar etmek yerine, on gün boyunca birer kez tekrar etmek çok daha kalıcıdır. Buna aralıklı tekrar etkisi denir.',
            'Bu bulgu, bir bahçıvanın sulama bilgisine benzer. Bir bitkiyi bir günde bir kova suyla boğmak yerine, her gün biraz sulamak onu besler.',
            '## Hatırlamak Yeniden Kurmaktır',
            'İngiliz psikolog Frederic Bartlett, 1930\'larda başka bir yönden yaklaştı. Deneklerine yabancı bir kültürden bir halk hikâyesi okutuyor, sonra onlardan bu hikâyeyi farklı zamanlarda yeniden anlatmalarını istiyordu.[[kaynak:'.$bartlett.']] Her anlatımda hikâye biraz daha değişiyordu: tanıdık olmayan ayrıntılar düşüyor, yerine anlatıcının kendi kültüründen öğeler geliyor, hikâye giderek daha "mantıklı" hale geliyordu.',
            'Bartlett\'in sonucu çarpıcıydı: Hatırlamak, geçmişi olduğu gibi geri çağırmak değil, onu bugünün bilgisiyle yeniden kurmaktır. Her hatırlayışta anıyı biraz değiştiririz.[[dn:Bu nedenle en canlı ve en emin olduğumuz anılar bile yanlış olabilir. Tanıklık araştırmaları, kişilerin tüm samimiyetleriyle hiç yaşanmamış ayrıntıları hatırlayabildiğini defalarca göstermiştir.]]',
            '## Unutmanın Hakkı',
            'Unutmayı genellikle bir kusur olarak görürüz. Oysa unutmak, hafızanın temel işlevlerinden biridir. Her şeyi hatırlayan bir zihin, ayrıntıların altında ezilirdi; önemli olanı önemsizden ayıramazdı. Unutmak, zihnin budama makasıdır.',
            'Bahçıvanlar bilir: budanmayan bir ağaç, meyve vermek yerine dal verir. Kuruyan dalları, fazla sürgünleri, birbirine dolanan kolları kesmek, ağacın gücünü asıl önemli yerlere yönlendirir. Zihin de unutarak ne olduğunu seçer.',
            '> Hafıza bir arşiv değil, bir bahçedir. Hatırladıklarımız kadar, unuttuklarımız da onun biçimini belirler.',
            'Bu bölümden çıkarılacak pratik sonuç belki şudur: Önemli bulduğunuz şeyleri tekrar tekrar ziyaret edin. Bir kitaptan altını çizdiğiniz cümleleri bir deftere yazın ve ara sıra o deftere dönün. Unutmanın doğal olduğunu kabul edin ama neyin unutulmaması gerektiğine siz karar verin.',
        ],
    ],
    [
        'title' => 'Kaygının Haritası',
        'blocks' => [
            'Kaygı, çağımızın en sık konuşulan duygularından biri. Kaygılı olduğumuzu söylüyoruz, kaygı bozukluklarından söz ediyoruz, kaygıyı azaltmanın yollarını arıyoruz. Ama kaygının ne olduğu, neden var olduğu ve bize ne anlatmaya çalıştığı üzerine daha az düşünüyoruz.',
            '## Özgürlüğün Baş Dönmesi',
            'Danimarkalı filozof Søren Kierkegaard, kaygıyı insan olmanın ayrılmaz bir parçası olarak görüyordu. 1844\'te yayımlanan kitabında kaygıyı özgürlüğün baş dönmesine benzetir.[[kaynak:'.$kierkegaard.']] Bir uçurumun kenarında durduğumuzda başımız döner; bunun nedeni yalnızca düşme ihtimali değil, atlama özgürlüğümüzün de olmasıdır.',
            'Kierkegaard\'a göre kaygı, seçim yapabilen bir varlık olmamızın bedelidir. Hayvanlar korkar ama kaygılanmaz; çünkü önlerinde açık duran olasılıklar yoktur. İnsan ise her an farklı biri olabileceğinin, farklı bir yol seçebileceğinin farkındadır ve bu farkındalık, belirsiz bir huzursuzluk yaratır.',
            '## Normal ve Nevrotik Kaygı',
            'Yirminci yüzyılda Amerikalı psikolog Rollo May, Kierkegaard\'ın izinden giderek kaygıyı ikiye ayırdı.[[kaynak:'.$may.']] Normal kaygı, gerçek bir tehdide orantılı bir tepkidir ve baskı altına alınmaz; bununla yüzleşilebilir, hatta yaratıcı bir enerjiye dönüştürülebilir. Nevrotik kaygı ise tehdide orantısızdır, bastırılır ve kişiyi daraltır.',
            'May\'in önemli bir tespiti, kaygıdan kaçmanın onu azaltmak yerine büyüttüğüydü. Kaygı verici durumlardan kaçındıkça, bu durumlarla baş edebileceğimize dair güvenimiz azalır ve kaygı daha geniş bir alana yayılır.[[dn:Bu döngü, bugün bilişsel davranışçı terapinin de temel kabullerinden biridir: kaçınma kısa vadede rahatlatır ama uzun vadede kaygıyı besler.]]',
            '## Haritayı Çizmek',
            'Kaygıyla çalışmanın bir yolu, onun haritasını çıkarmaktır. Bir kâğıt alın ve sizi kaygılandıran şeyleri yazın. Sonra her birinin yanına iki soru sorun: Bu şey gerçekten olabilir mi? Olursa, ne yapabilirim?',
            'Bu basit alıştırma, kaygının belirsiz bulutunu somut adımlara dönüştürür. Bazı kaygıların neredeyse hiç gerçekleşme ihtimali olmadığını, bazılarının ise gerçek ama baş edilebilir olduğunu görürsünüz. Harita, araziyi değiştirmez ama arazide kaybolmanızı önler.',
            '### Bedenin Dili',
            'Kaygı yalnızca zihinde yaşanmaz; bedende de yer tutar. Hızlanan kalp, sığlaşan nefes, gerilen omuzlar. Bu bedensel tepkiler, atalarımızı tehlikeden korumak için evrilmiş bir alarm sisteminin parçasıdır. Sorun, bu alarmın bugün e-postalar ve toplantılar için de çalmasıdır.',
            'Nefesi yavaşlatmak, bu alarmı kısmanın en eski yollarından biridir. Dört saniye nefes almak, dört saniye tutmak, altı saniyede vermek. Bunu birkaç kez tekrarlamak, bedene tehlikenin geçtiği mesajını verir.',
            '> Kaygı bir düşman değil, bir habercidir. Mesajını dinlemeden kapıdan kovmaya çalışırsak, pencereden geri gelir.',
        ],
    ],
    [
        'title' => 'Alışkanlık Bahçıvanlığı',
        'blocks' => [
            'Günümüzün büyük bölümünü alışkanlıklar yönetir. Sabah hangi taraftan yataktan kalktığımız, dişlerimizi nasıl fırçaladığımız, işe hangi yoldan gittiğimiz, akşam eve girince ilk ne yaptığımız. Bunların çoğunu düşünmeden yaparız ve bu iyi bir şeydir: her küçük eylem için karar vermek zorunda kalsaydık, zihnimiz daha öğlene varmadan tükenirdi.',
            'Ama aynı mekanizma, istemediğimiz davranışları da otomatik hale getirir. Canımız sıkıldığında elimizin telefona gitmesi, stresliyken bir şeyler atıştırmak, akşamları ekran başında saatlerin nasıl geçtiğini fark etmemek.',
            '## Alışkanlık Döngüsü',
            'Gazeteci Charles Duhigg, alışkanlık araştırmalarını derlediği kitabında basit bir model önerir: her alışkanlık bir işaret, bir rutin ve bir ödülden oluşan bir döngüdür.[[kaynak:'.$duhigg.']] İşaret davranışı tetikler, rutin davranışın kendisidir, ödül ise beyne bu döngünün tekrarlanmaya değer olduğunu söyler.',
            'Örneğin öğleden sonra saat üçte yorgunluk hissi (işaret) sizi kafeteryaya götürüp bir tatlı almaya (rutin) yöneltir ve kısa bir enerji ile sosyalleşme hissi (ödül) sağlar. Bu döngü yeterince tekrarlandığında, saat üç olur olmaz ayaklarınız sizi kafeteryaya götürür.',
            '## Yabani Otları Değil, Toprağı Değiştirmek',
            'Kötü bir alışkanlığı bırakmaya çalışırken çoğumuz yabani otu çekip atmaya uğraşırız: iradeyle, yasaklarla, kendimize söz vererek. Ama bahçıvanlar bilir ki yabani ot çekilse de toprak aynıysa yeniden çıkar.',
            'Duhigg\'in modeli farklı bir yol önerir: işareti ve ödülü aynı bırakıp rutini değiştirmek. Saat üçteki yorgunluk hissi geldiğinde tatlı yerine kısa bir yürüyüş yapmak ya da bir arkadaşla beş dakika sohbet etmek. İhtiyaç aynı kalır, onu karşılama biçimi değişir.[[dn:Bu yöntemin işe yaraması için önce ödülün ne olduğunu doğru tespit etmek gerekir. Tatlı almaya giden kişinin asıl ihtiyacı şeker değil, işe verilen kısa bir ara ya da insan teması olabilir.]]',
            '## Küçük Tohumlar',
            'Yeni bir alışkanlık edinmek istiyorsak, büyük hedefler yerine küçük tohumlarla başlamak daha etkilidir. Her gün bir saat okumak yerine her gün iki sayfa okumak. Her gün koşmak yerine her gün ayakkabıları giyip kapıdan çıkmak.',
            'Bu küçüklük önemsiz görünebilir ama bir alışkanlığın kök salması için gereken şey süre değil, tekrardır. Küçük bir eylem her gün tekrarlandığında, zamanla kendiliğinden büyür. İki sayfa beş sayfaya, kapıdan çıkmak kısa bir yürüyüşe dönüşür.',
            '### Çevre Tasarımı',
            'Alışkanlıklarımızı belirleyen şeylerden biri de çevremizdir. Telefonu yatak odasının dışında şarj etmek, kitabı yastığın üstüne bırakmak, meyveyi tezgâhın görünür bir yerine koymak. Çevremizi, istediğimiz davranışın kolay, istemediğimizin zor olacağı şekilde düzenlemek, iradeye güvenmekten çok daha etkilidir.',
            '> İyi bir bahçıvan her gün bahçeyle savaşmaz. Toprağı bir kez doğru hazırlar, sonra bahçenin kendi işini yapmasına izin verir.',
        ],
    ],
    [
        'title' => 'Yalnızlık ile Tek Başınalık',
        'blocks' => [
            'Fransız düşünür Blaise Pascal, on yedinci yüzyılda şu gözlemi yapmıştı: İnsanların bütün mutsuzluğu tek bir şeyden gelir; bir odada sessizce oturamamalarından.[[kaynak:'.$pascal.']] Pascal\'ın zamanında ne telefon ne televizyon vardı. Yine de insanlar, kendileriyle baş başa kalmaktan kaçmak için oyunlara, eğlencelere, bitmeyen meşguliyetlere sığınıyordu.',
            'Bugün bu kaçışın araçları çok daha güçlü. Bir anlık boşlukta bile, bir kuyrukta beklerken, bir asansörde, bir kırmızı ışıkta, elimiz telefona gider. Sessizlik, doldurulması gereken bir boşluk gibi hissettirir.',
            '## İki Ayrı Deneyim',
            'Türkçede bu iki deneyimi ayırmak için iki kelimemiz var: yalnızlık ve tek başınalık. Yalnızlık, istemediğimiz bir kopukluk hissidir; başkalarıyla bağ kurmak istediğimiz halde kuramamanın acısı. Tek başınalık ise kendi seçimimizle kendimizle kalmaktır; bir yürüyüş, bir okuma, bir düşünme zamanı.',
            'Bu ayrım önemlidir, çünkü biri bizi yaralarken öteki besler. Uzun süreli yalnızlık, araştırmaların defalarca gösterdiği gibi, hem ruh hem beden sağlığı için ciddi bir risktir. Ama seçilmiş tek başınalık, yaratıcılığın, iç huzurun ve kendini tanımanın zeminidir.',
            '## Yalnız Kalabilme Yetisi',
            'İngiliz psikanalist Donald Winnicott, 1958\'de kısa ama etkili bir makale yayımladı. Makalenin konusu, yalnız kalabilme yetisiydi.[[kaynak:'.$winnicott.']] Winnicott\'a göre bu yeti, çocuklukta, bir başkasının varlığında yalnız kalabilme deneyimiyle gelişir: bebek, annesi yanında ama onunla ilgilenmediği bir anda oyununa dalabildiğinde, güvenli bir yalnızlık yaşar.',
            'Bu paradoks çok şey anlatır: Yalnız kalabilmek için önce başkalarıyla güvenli bir bağ kurmuş olmamız gerekir. Bağları güvenli olan kişi, tek başına kaldığında terk edilmiş hissetmez; kendi iç dünyasında dinlenebilir.',
            '## Kendine Dönüş',
            'Psikiyatrist Anthony Storr, tek başınalığı savunan kitabında, insan mutluluğunun yalnızca yakın ilişkilerden gelmediğini öne sürer.[[kaynak:'.$storr.']] Pek çok insan için anlamın önemli bir kaynağı, tek başına yapılan işlerdir: yazmak, bestelemek, bir zanaatla uğraşmak, bir bahçeyi büyütmek.',
            'Storr, tarihteki pek çok yaratıcı insanın uzun tek başınalık dönemlerinden beslendiğini hatırlatır. Ama tek başınalığın değeri yalnızca dâhiler için değildir. Hepimizin, kendi düşüncelerimizi duyabileceğimiz, günün gürültüsünden arınabileceğimiz zamanlara ihtiyacı vardır.[[dn:Storr\'un yaklaşımı ilişkileri küçümsemez; yalnızca insan mutluluğunun tek bir kaynağa bağlanmasının kırılgan olduğunu, ilişkilerle tek başına yapılan anlamlı işlerin birbirini tamamladığını savunur.]]',
            '---',
            'Bu bölümün önerisi belki en zor olanı: Haftada bir kez, yarım saat bile olsa, hiçbir şey yapmadan tek başınıza oturun. Müzik yok, kitap yok, telefon yok. Yalnızca siz ve düşünceleriniz. İlk seferinde bu yarım saat çok uzun gelecek. Zamanla, bahçenin en sessiz köşesine dönüşecek.',
            '> Tek başınalık, kendimize bir mektup yazmak gibidir. Önce ne yazacağımızı bilemeyiz; sonra kalemin kendiliğinden yürüdüğünü görürüz.',
        ],
    ],
    [
        'title' => 'Sonsöz: Bahçeye Dönmek',
        'blocks' => [
            'Bu kitabın başında zihni bir bahçeye benzetmiştik. Şimdi, altı bölümün sonunda, o bahçede kısa bir yürüyüş yapalım.',
            'Dikkat, bahçenin kapısıdır. Neye dikkat edersek, bahçemize o girer. Yavaşlık, bahçenin mevsimidir; hiçbir şey acele ettirilemez. Hafıza, bahçenin toprağıdır; sürekli yenilenir, budanır, yeniden şekillenir. Kaygı, bahçenin hava durumudur; fırtınaları da vardır, onları durduramayız ama nasıl karşılayacağımızı seçebiliriz. Alışkanlıklar, bahçenin patikalarıdır; ne kadar çok yürünürse o kadar belirginleşir. Tek başınalık ise bahçenin en sessiz köşesidir; orada oturup yalnızca dinleriz.',
            'Hiçbir bahçe bir günde kurulmaz. Ve hiçbir bahçe bir kez kurulduktan sonra kendi haline bırakılamaz. Bahçıvanlık bir sonuç değil, bir süreçtir; her gün biraz su, biraz budama, biraz bekleme.',
            'Bu kitaptaki öneriler de böyle okunmalı. Hepsini birden uygulamaya çalışmayın. Birini seçin, belki en kolayını, ve birkaç hafta onunla yaşayın. Sonra bir başkasını ekleyin. Bahçeniz yavaş yavaş, ama kendi ritminde değişecek.',
            'Son olarak bir hatırlatma: Zihnin bahçesi ile ilgilenmek, dünyadan el etek çekmek anlamına gelmez. Tam tersine, iç bahçesine özen gösteren kişi, dış dünyaya daha dikkatli, daha sabırlı ve daha cömert bakar. Kendi toprağını tanıyan, başkasının toprağına da saygı duyar.',
            '> Bahçeye dönmek, kendimize dönmektir. Ve kendimize döndüğümüzde, dünyaya da yeniden, daha dikkatle bakabiliriz.',
            'Okuduğunuz için teşekkür ederim.',
        ],
    ],
    [
        'title' => 'Kaynakça',
        'blocks' => [
            'Metinde rakamla işaretlenen kaynaklar, ilk geçtikleri sırayla:',
            '@kaynakca',
        ],
    ],
];
