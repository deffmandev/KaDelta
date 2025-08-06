<style>
body
{
        background: #2f363c;
        color: white;
        padding-top: 190px; /* Ajustez selon la hauteur de votre FrameTitre */

}

.Vignette
{
    display:none;
    border: 1px solid #716f6f;
    display: block;
    height: 80px;
    width: 180px;
    padding: 14px;
    position: relative;
    box-shadow: inset 4px 4px 10px 2px #00000087;
    margin: 14px;
}

.ViOff
{
    background: none;
}

.ViOnClim
{
    background: linear-gradient(#565eb7, #4451eb87, #565eb7),
    url('images/clim.png') no-repeat center/100px 100px;
}

.ViOnDefaut
{
    background:
        linear-gradient(#ed0000, #ff6d3496, #b90e0e),
        url('images/defaut.png') no-repeat center/100px 100px;
}

.ViOnChaud
{
    background: linear-gradient(#cccd21, #e9ac38c4, #bfb42d),
        url('images/chaud.png') no-repeat center/100px 100px;

}

.ViOnDry
{
    background:linear-gradient(#e5efdb, #d5c7acc4, #a7a69fa6), 
        url(images/dry.png) no-repeat center / 100px 100px
}

.ViOnFan
{
    background:linear-gradient(#cd7bcc, #c094c3a8, #8c6397), 
        url(images/fan.png) no-repeat center / 100px 100px
}

.ViOnAuto
{
    background:linear-gradient(#4e85d5f0, #8da8e5d1, #dfbddfa3, #dd7e7e), 
        url(images/auto.png) no-repeat center / 100px 100px
}


.ViT1
{
    top: 8px;
    left: 6px;
    position: absolute;
    font-size: 23px;
    text-shadow: 2px 1px 2px black;
    font-family: math;
    width: 96%;
    height: 26px;
    overflow: hidden;
}

.ViT2
{
    top: 37px;
    left: 19px;
    position: absolute;
    font-size: 25px;
    font-weight: 900;
    font-family: math;
}

.ViT3
{
    top: 73px;
    right: 7px;
    position: absolute;
    font-size: 19px;
    font-weight: 100;
    font-family: math;
    width: 83px;
    text-align: end;
}

.ViT4
{
    top: 63px;
    left: 8px;
    position: absolute;
    font-size: 20px;
    font-weight: 100;
    font-family: math;
}

.fan1
{
        background: linear-gradient(150deg, #00000085, #0000002b 60%, #c1ababad 100%), url(images/fan1.png) no-repeat center center;
        background-size: 100% 100%, 32px 32px;
        border-radius: 43px;
        width: 42px;
        height: 40px;

}
.fan2
{
        background: linear-gradient(150deg, #00000085, #0000002b 60%, #c1ababad 100%), url(images/fan2.png) no-repeat center center;
        background-size: 100% 100%, 32px 32px;
        border-radius: 43px;
        width: 42px;
        height: 40px;
}
.fan3
{
        background: linear-gradient(150deg, #00000085, #0000002b 60%, #c1ababad 100%), url(images/fan3.png) no-repeat center center;
        background-size: 100% 100%, 32px 32px;
        border-radius: 43px;
        width: 42px;
        height: 40px;
}
.fan4
{
        background: linear-gradient(150deg, #00000085, #0000002b 60%, #c1ababad 100%), url(images/fan4.png) no-repeat center center;
        background-size: 100% 100%, 32px 32px;
        border-radius: 43px;
        width: 42px;
        height: 40px;
}

.ViT5
{
    display: none;
}

.CadreUnites
{
    display: flex;
    flex-wrap: wrap;
    padding: 9px;
    margin: auto;
    flex-direction: row;
    justify-content: flex-start;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
}

.OverScreen
{
    display : none;
    position: fixed;
    top: 0;
    left: 0;
    background: rgb(55 82 116 / 44%);
    backdrop-filter: blur(5px);
    width: 100%;
    height: 100%;
    z-index: 1000;
}


.FrameTitre
{
    display: flex;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    box-shadow: 1px 1px red;
    height: 196px;
    z-index: 100;
    background: #2f363c;
    box-shadow: 4px 6px 5px #0000007d;
}

.TitreGroupe
{
    position: absolute;
    top: 95px;
    left: 173px;
    display: flex;
    flex-direction: row;
    align-content: center;
    justify-content: center;
}


.groupe-btn 
{
    background: linear-gradient(79deg, #6a11cb 0%, #2575fc 100%);
    color: #fff;
    padding: 16px 36px;
    border: none;
    border-radius: 20px;
    font-size: 1.0rem;
    font-weight: bold;
    box-shadow: 2px 3px 20px rgba(38, 50, 56, 0.2);
    cursor: pointer;
    transition: transform 0.15s, box-shadow 0.15s;
    outline: none;
    letter-spacing: 1px;
    margin-right: 2em;
}
.groupe-btn:hover, .groupe-btn:hover 
        {
            transform: translateY(-2px) scale(1.04);
            box-shadow: 0 8px 32px rgba(38, 50, 56, 0.25);
            background: linear-gradient(90deg, #2575fc 0%, #6a11cb 100%);
        }

.horloges
{
    position: absolute;
    top: 19px;
    right: 32px;
    font-size: 1.4em;
    padding: 6px;
    font-family: math;
    letter-spacing: 1px;
    color: #fff;
    font-weight: 400;
    text-align: center;
    z-index: 100;
}

</style>